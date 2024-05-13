<?php

declare (strict_types=1);

namespace MakinaCorpus\Profiling\Prometheus\Storage;

use MakinaCorpus\Profiling\Prometheus\Output\SampleCollection;
use MakinaCorpus\Profiling\Prometheus\Output\SummaryOutput;
use MakinaCorpus\Profiling\Prometheus\Sample\Counter;
use MakinaCorpus\Profiling\Prometheus\Sample\Gauge;
use MakinaCorpus\Profiling\Prometheus\Sample\Sample;
use MakinaCorpus\Profiling\Prometheus\Sample\Summary;
use MakinaCorpus\Profiling\Prometheus\Sample\SummaryItem;
use MakinaCorpus\Profiling\Prometheus\Schema\Schema;
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
                type: 'gauge',
                labelNames: $meta->getLabelNames(),
                samples: $items,
            );
        });

        // Summary.
        yield from $this->scanKeys('s:', function (string $key) use ($schema, $redis, $prefixLen) {
            $name = \substr($key, $prefixLen);
            $meta = $schema->getSummary($name, true);

            $items = (function () use ($key, $redis, $name, $meta) {;
                foreach ((array) $redis->hGetAll($key) as $encodedLabels => $serial) {
                    if ('_seq' === $encodedLabels) {
                        continue;
                    }

                    $labels = \json_decode($encodedLabels) ?? [];

                    $itemKeyPrefix = 'c:s:' . $name . ':' . $serial;
                    $values = \iterator_to_array($this->scanKeys($itemKeyPrefix, fn ($key) => $redis->get($key)));
                    \sort($values);

                    foreach ($meta->getQuantiles() as $quantile) {
                        // Compute quantiles and set a summary sample in list for
                        // each computed quantile.
                        yield (new SummaryOutput($name, $labels, [], SummaryMeta::computeQuantiles($values, $quantile), $quantile));
                    }

                    yield (new Counter($name . '_count', $labels, []))->increment(\count($values));
                    yield (new Gauge($name . '_sum', $labels, []))->set(\array_sum($values));
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

        $match = ($this->keyPrefix ? $this->keyPrefix . ':' : '') . $match;

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
    private function getKeyName(Sample $sample, bool $asItem = false): string
    {
        $infix = $asItem ? 'i:' : '';

        if ($sample instanceof Counter) {
            $infix .= 'c:';
        } else if ($sample instanceof Gauge) {
            $infix .= 'g:';
        } else if ($sample instanceof Summary) {
            $infix .= 's:';
        } else {
            \trigger_error(\sprintf("Sample of type '%s' is not supported.", \get_class($sample)), E_USER_WARNING);
            $infix .= 'e:';
        }

        return $this->getKey($infix . $sample->name);
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
