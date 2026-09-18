<?php

namespace Anima\Tests;

class FakeRedis
{
    public array $storage = [];
    public array $sortedSets = [];
    public array $ttls = [];

    public function set(string $key, string $value): void
    {
        $this->storage[$key] = $value;
    }

    public function setex(string $key, int $ttl, string $value): void
    {
        $this->storage[$key] = $value;
        $this->ttls[$key] = $ttl;
    }

    public function get(string $key): ?string
    {
        return $this->storage[$key] ?? null;
    }

    public function del(string|array $keys): int
    {
        $keys = is_array($keys) ? $keys : [$keys];
        $deleted = 0;
        foreach ($keys as $key) {
            if (array_key_exists($key, $this->storage)) {
                $deleted++;
            }
            unset($this->storage[$key], $this->ttls[$key], $this->sortedSets[$key]);
        }

        return $deleted;
    }

    public function zadd(string $key, mixed $arg1, mixed $arg2 = null): void
    {
        if (! isset($this->sortedSets[$key])) {
            $this->sortedSets[$key] = [];
        }

        if (is_array($arg1)) {
            foreach ($arg1 as $member => $score) {
                $this->sortedSets[$key][$member] = (float) $score;
            }
        } elseif ($arg2 !== null) {
            $score = (float) $arg1;
            $member = (string) $arg2;
            $this->sortedSets[$key][$member] = $score;
        }
    }

    public function zcard(string $key): int
    {
        return count($this->sortedSets[$key] ?? []);
    }

    public function zrevrange(string $key, int $start, int $stop): array
    {
        if (! isset($this->sortedSets[$key])) {
            return [];
        }

        $set = $this->sortedSets[$key];
        arsort($set);
        $members = array_keys($set);

        $length = ($stop === -1) ? null : ($stop - $start + 1);

        return array_slice($members, $start, $length);
    }

    public function zrem(string $key, string $member): void
    {
        unset($this->sortedSets[$key][$member]);
    }

    public function zremrangebyscore(string $key, string|float $min, string|float $max): void
    {
        if (! isset($this->sortedSets[$key])) {
            return;
        }

        $min = $min === '-inf' ? -INF : (float) $min;
        $max = $max === '+inf' ? INF : (float) $max;

        foreach ($this->sortedSets[$key] as $member => $score) {
            if ($score >= $min && $score <= $max) {
                unset($this->sortedSets[$key][$member]);
            }
        }
    }

    public function mget(array $keys): array
    {
        return array_map(fn ($key) => $this->storage[$key] ?? null, $keys);
    }
}
