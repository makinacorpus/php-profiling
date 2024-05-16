<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Storage;

use MakinaCorpus\Profiling\Prometheus\Output\SampleCollection;
use MakinaCorpus\Profiling\Prometheus\Sample\Counter;
use MakinaCorpus\Profiling\Prometheus\Sample\Gauge;
use MakinaCorpus\Profiling\Prometheus\Sample\Histogram;
use MakinaCorpus\Profiling\Prometheus\Sample\HistogramItem;
use MakinaCorpus\Profiling\Prometheus\Sample\Sample;
use MakinaCorpus\Profiling\Prometheus\Sample\Summary;
use MakinaCorpus\Profiling\Prometheus\Sample\SummaryItem;
use MakinaCorpus\Profiling\Prometheus\Schema\AbstractMeta;
use MakinaCorpus\Profiling\Prometheus\Schema\Schema;
use MakinaCorpus\Profiling\Prometheus\Schema\CounterMeta;
use MakinaCorpus\Profiling\Prometheus\Schema\GaugeMeta;
use MakinaCorpus\Profiling\Prometheus\Schema\HistogramMeta;
use MakinaCorpus\Profiling\Prometheus\Schema\SummaryMeta;

/**
 * Schema is the following, first, we don't store any metadata in Redis since
 * we have a schema of our own in memory right here. So, only values will be
 * in there.
 *
 * All samples start with some Redis HASH whose name starts with "PREFIX:TYPE:"
 * where TYPE can be either one of "c", "g", "s" or "h" respectively for counter
 * gauge, summary or histogram. It allows us to collect all values using a
 * simple LUA script that does a wildcard key search.
 *
 * For counter or gauge samples, there is a unique Redis HASH created, each
 * hash entry key is the serialized label values, and value is the gauge or
 * counter value:
 *
 *   "PREFIX:(c|g):NAME" = HASH
 *       "_JSON_ENCODED_LABELS" = value
 *       "_JSON_ENCODED_LABELS" = value
 *       ...
 *
 * Internally, we will always work with float values, no matter it's integer
 * or floats, it makes the Redis LUA code simpler, using hIncrByFloat always.
 *
 * For histogram, we need to store one HASH for each label values couple
 * because each one of those hashes will keep all bucket values.
 *
 *   "PREFIX:h:NAME" = HASH
 *       "_seq" = current serial
 *       "_JSON_ENCODED_LABELS" = serial
 *       "_JSON_ENCODED_LABELS" = serial
 *       ...
 *
 *   "PREFIX:i:h:NAME:SERIAL" = [bucket: count, bucket: count, _sum: value]
 *   ...
 *
 * For summary, schema is more complex because we need to store each value
 * independly with a lifetime. The main HASH will act as a key index for
 * fetching all individual value.
 *
 *   "PREFIX:s:NAME" = HASH
 *       "_seq" = current serial
 *       "_JSON_ENCODED_LABELS" = serial
 *       "_JSON_ENCODED_LABELS" = serial
 *       ...
 *
 *   "PREFIX:i:s:NAME:SERIAL:RANDOM" = value, with expiry
 *   ...
 *
 * @todo
 *   - explore performance improvement by using pipelines whenever possible
 *   - explore performance improvement by lua-scripting more stuff
 */
class RedisStorage extends AbstractStorage
{
    private string $redisUri;
    private ?\Redis $redis = null;

    public function __construct(
        string|\Redis $redisUri,
        private string $keyPrefix = 'symfony_prometheus',
    ) {
        if ($redisUri instanceof \Redis) {
            $this->redis = $redisUri;
            $this->redisUri = 'invalidurl';
        } else {
            $this->redisUri = $redisUri;
        }
    }

    #[\Override]
    public function collect(Schema $schema): iterable
    {
        $redis = $this->getRedisClient();
        $prefixLen = \strlen($this->keyPrefix) + 3; // 3 is ":X:".

        // Gauge.
        yield from $this->scanKeys('g:', function (string $key) use ($schema, $redis, $prefixLen) {
            $name = \substr($key, $prefixLen);
            $meta = $schema->getGauge($name, true);

            $items = [];
            foreach ((array) $redis->hGetAll($key) as $encodedLabels => $value) {
                $items[] = (new Gauge($name, \json_decode($encodedLabels), []))->set((float) $value);
            }

            yield new SampleCollection(
                name: $name,
                help: $meta->getHelp(),
                type: 'gauge',
                labelNames: $meta->getLabelNames(),
                samples: $items,
            );
        });

        // Counter.
        yield from $this->scanKeys('c:', function (string $key) use ($schema, $redis, $prefixLen) {
            $name = \substr($key, $prefixLen);
            $meta = $schema->getGauge($name, true);

            $items = [];
            foreach ((array) $redis->hGetAll($key) as $encodedLabels => $value) {
                $items[] = (new Counter($name, \json_decode($encodedLabels), []))->increment((int) $value);
            }

            yield new SampleCollection(
                name: $name,
                help: $meta->getHelp(),
                type: 'counter',
                labelNames: $meta->getLabelNames(),
                samples: $items,
            );
        });

        // Histogram.
        yield from $this->scanKeys('h:', function (string $key) use ($schema, $redis, $prefixLen) {
            $name = \substr($key, $prefixLen);
            $meta = $schema->getHistogram($name, true);

            $items = (function () use ($key, $redis, $name, $meta) {
                $itemKeyPrefix = $this->getKeyName($meta, true);

                foreach ((array) $redis->hGetAll($key) as $encodedLabels => $serial) {
                    if ('_seq' === $encodedLabels) {
                        continue;
                    }

                    $labelValues = \json_decode($encodedLabels) ?? [];
                    $itemKey = $itemKeyPrefix . ':' . $serial;
                    $sum = 0.0;

                    $values = [];
                    foreach ($redis->hGetAll($itemKey) as $bucket => $value) {
                        if ('_sum' === $bucket) {
                            $sum = (float) $value;
                        } else {
                            $values[$bucket] = [$value, 0.0];
                        }
                    }
                    // Move total sum to +Inf value, in the end it won't change
                    // how the _sum value is computed. This allows us to store a
                    // single sum value for each (sample, labels) couple instead
                    // of for each bucket. This simplifies greatly the schema.
                    if (isset($values['+Inf'])) {
                        $values['+Inf'][1] += $sum;
                    } else {
                        $values['+Inf'] = [0, $sum];
                    }

                    yield from $meta->createOutput($name, $labelValues, $values);
                }
            })();

            yield new SampleCollection(
                name: $name,
                help: $meta->getHelp(),
                type: 'histogram',
                labelNames: $meta->getLabelNames(),
                samples: $items,
            );
        });

        // Summary.
        yield from $this->scanKeys('s:', function (string $key) use ($schema, $redis, $prefixLen) {
            $name = \substr($key, $prefixLen);
            $meta = $schema->getSummary($name, true);

            $items = (function () use ($key, $redis, $name, $meta) {
                $itemKeyPrefix = $this->getKeyName($meta, true);

                foreach ((array) $redis->hGetAll($key) as $encodedLabels => $serial) {
                    if ('_seq' === $encodedLabels) {
                        continue;
                    }

                    $labelValues = \json_decode($encodedLabels) ?? [];
                    $itemKeyPrefix = $itemKeyPrefix . ':' . $serial;
                    $values = \iterator_to_array($this->scanKeys($itemKeyPrefix, fn ($key) => $redis->get($key)));

                    yield from $meta->createOutput($name, $labelValues, $values);
                }
            })();

            yield new SampleCollection(
                name: $name,
                help: $meta->getHelp(),
                type: 'summary',
                labelNames: $meta->getLabelNames(),
                samples: $items,
            );
        });
    }

    #[\Override]
    public function store(Schema $schema, iterable $samples): void
    {
        $redis = $this->getRedisClient();

        foreach ($samples as $sample) {
            \assert($sample instanceof Sample);

            $rootHashKey = $this->getKeyName($sample);
            $labelsKey = \json_encode($sample->labelValues);

            if ($sample instanceof Counter) {
                $redis->hIncrBy($rootHashKey, $labelsKey, $sample->getValue());
            } else if ($sample instanceof Gauge) {
                $redis->hSet($rootHashKey, $labelsKey, $sample->getValue());
            } else if ($sample instanceof Histogram) {
                $meta = $schema->getHistogram($sample->name);
                $itemKeyPrefix = $this->getKeyName($sample, true);

                if (!$serial = $redis->hGet($rootHashKey, $labelsKey)) {
                    $serial = $redis->hIncrBy($rootHashKey, '_seq', 1);
                    $redis->hSet($rootHashKey, $labelsKey, $serial);
                }

                foreach ($sample->getValues() as $value) {
                    \assert($value instanceof HistogramItem);
                    $itemKey = $itemKeyPrefix . ':' . $serial;
                    $redis->hIncrBy($itemKey, (string) $meta->findBucketFor($value->value), 1);
                    $redis->hIncrByFloat($itemKey, '_sum', $value->value);
                }
            } else if ($sample instanceof Summary) {
                $meta = $schema->getSummary($sample->name);
                $itemKeyPrefix = $this->getKeyName($sample, true);

                if (!$serial = $redis->hGet($rootHashKey, $labelsKey)) {
                    $serial = $redis->hIncrBy($rootHashKey, '_seq', 1);
                    $redis->hSet($rootHashKey, $labelsKey, $serial);
                }

                foreach ($sample->getValues() as $value) {
                    \assert($value instanceof SummaryItem);
                    $random = \bin2hex(\random_bytes(6));
                    $itemKey = $itemKeyPrefix . ':' . $serial . ':' . $random;
                    $redis->setex($itemKey, $meta->getMaxAge(), $value->value);
                }
            } else {
                \trigger_error(\sprintf("Sample of type '%s' is not supported.", \get_class($sample)), E_USER_WARNING);
            }
        }
    }

    #[\Override]
    public function cleanOutdatedSamples(): void
    {
        // Nothing to do here, because we set expiry on every value.
    }

    #[\Override]
    public function wipeOutData(): void
    {
        $redis = $this->getRedisClient();

        if (!$this->keyPrefix) {
            $redis->flushAll();

            return;
        }

        $globalPrefix = $redis->getOption(\Redis::OPT_PREFIX);
        // @phpstan-ignore-next-line
        if (\is_string($globalPrefix)) {
            $searchPattern = $globalPrefix;
        } else {
            $searchPattern = "";
        }
        $searchPattern .= $this->keyPrefix . '*';

        $redis->eval(
            <<<LUA
            redis.replicate_commands()
            local cursor = "0"
            repeat
                local results = redis.call('SCAN', cursor, 'MATCH', ARGV[1])
                cursor = results[1]
                for _, key in ipairs(results[2]) do
                    redis.call('DEL', key)
                end
            until cursor == "0"
            LUA,
            [$searchPattern],
            0,
        );
    }

    #[\Override]
    protected function doEnsureSchema(): void
    {
        // There's probably nothing to do here.
    }

    /**
     * Iterate on keys.
     */
    private function scanKeys(string $match, callable $callback): iterable
    {
        $redis = $this->getRedisClient();

        if ($this->keyPrefix && !\str_starts_with($match, $this->keyPrefix)) {
            $match = $this->keyPrefix . ':' . $match;
        }

        $cursor = null;
        do {
            if (false !== ($keys = $redis->scan($cursor, $match . '*'))) {
                foreach ($keys as $key) {
                    if (null !== ($ret = $callback($key))) {
                        if (\is_iterable($ret)) {
                            yield from $ret;
                        } else {
                            yield $ret;
                        }
                    }
                }
            } else {
                break; // Failsafe, just in case.
            }
        } while ($cursor > 0);
    }

    /**
     * Get key name that contains values to read.
     */
    private function getKeyName(Sample|AbstractMeta $sample, bool $asItem = false): string
    {
        $name = null;
        $infix = $asItem ? 'i:' : '';

        if ($sample instanceof Counter) {
            $infix .= 'c:';
            $name = $sample->name;
        } else if ($sample instanceof Gauge) {
            $infix .= 'g:';
            $name = $sample->name;
        } else if ($sample instanceof Histogram) {
            $infix .= 'h:';
            $name = $sample->name;
        } else if ($sample instanceof Summary) {
            $infix .= 's:';
            $name = $sample->name;
        } else if ($sample instanceof CounterMeta) {
            $infix .= 'c:';
            $name = $sample->getName();
        } else if ($sample instanceof GaugeMeta) {
            $infix .= 'g:';
            $name = $sample->getName();
        } else if ($sample instanceof HistogramMeta) {
            $infix .= 'h:';
            $name = $sample->getName();
        } else if ($sample instanceof SummaryMeta) {
            $infix .= 's:';
            $name = $sample->getName();
        } else {
            \trigger_error(\sprintf("Sample of type '%s' is not supported.", \get_class($sample)), E_USER_WARNING);
            $infix .= 'e:';
            $name = $sample instanceof Sample ? $sample->name : $sample->getName();
        }

        return $this->getKey($infix . $name);
    }

    /**
     * Get key name that contains values to read.
     */
    private function getKey(string $name): string
    {
        return ($this->keyPrefix ? $this->keyPrefix . ':' : '') . $name;
    }

    /**
     * Get database session.
     */
    private function getRedisClient(): \Redis
    {
        if ($this->redis) {
            return $this->redis;
        }

        $defaults = [
            'database' => null,
            'host' => '127.0.0.1',
            'password' => null,
            'persistent' => true,
            'port' => 6379,
            'read_timeout' => 10,
            'ssl' => false,
            'ssl_verify_peer' => true,
            'timeout' => 0.1,
            'username' => null,
        ];

        // Parse user input.
        $data = \parse_url($this->redisUri);
        $options = [];
        if (isset($data['query'])) {
            \parse_str($data['query'], $options);
        }

        // Parse and validate all options.
        $options += [
            'host' => $data['host'],
        ];
        if (isset($data['scheme'])) {
            if ($data['scheme'] === 'redis') {
            } else if ($data['scheme'] === 'tls') {
                $options['ssl'] = true;
            } else {
                throw new \InvalidArgumentException("Redis URL scheme must be 'redis://' or 'tls://'");
            }
        }
        if (isset($data['port'])) {
            $options['port'] = (int) $data['port'];
        }
        if (isset($data['user'])) {
            $options['username'] = $data['user'];
        }
        if (isset($data['pass'])) {
            $options['password'] = $data['pass'];
        }
        if (isset($options['ssl_verify_peer'])) {
            $options['ssl_verify_peer'] = (bool) $options['ssl_verify_peer'];
        }
        if (isset($options['read_timeout'])) {
            $options['read_timeout'] = (int) $options['read_timeout'];
        }
        if (isset($options['timeout'])) {
            $options['timeout'] = (float) $options['timeout'];
        }
        $options = \array_replace($defaults, $options);

        // Create client.
        $this->redis = new \Redis();
        // TLS support.
        $url = ($options['ssl'] ? 'tls://' : '') . $options['host'];
        // Persistent or non-persistent connection.
        if ($options['persistent']) {
            $this->redis->pconnect($url, $options['port'], $options['timeout']);
        } else {
            $this->redis->connect($url, $options['port'], $options['timeout']);
        }
        // AUTH support.
        if ($options['password'] || $options['username']) {
            $this->redis->auth(\array_filter(['user' => $options['username'], 'pass' => $options['password']]));
        }
        // SELECT database support.
        if ($options['database']) {
            $this->redis->select($options['database']);
        }

        return $this->redis;
    }
}
