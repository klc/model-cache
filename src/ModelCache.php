<?php

namespace KLC;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

trait ModelCache
{
    public function scopeGetInCache($query)
    {
        return $this->inCache($query);
    }

    public function scopeFirstInCache($query)
    {
        $query->limit(1);

        return $this->inCache($query)->first();
    }

    public function scopeFindInCache($query, $id)
    {
        $query->where('id', $id);

        return $query->firstInCache();
    }

    public function scopeCacheForget($query)
    {
        $cacheKey = $this->getCacheKey($query);

        Cache::forget($cacheKey);
    }

    private function inCache($query)
    {
        $cacheKey = $this->getCacheKey($query);
        $ttl = $this->getTtl();
        $store = $this->getStore();

        $data = Cache::store($store)
            ->remember($cacheKey, $ttl, function () use ($query) {
            return $query->get()->toJson();
        });

        $dataArr = json_decode($data, true);

        Model::unguard();
        $result = $this->newQuery()->hydrate($dataArr);
        Model::unguard();

        return $result;
    }

    private function getTtl()
    {
        if (!isset(static::$ttl)) {
            return config('cache.model.default_ttl', 300);
        }

        return static::$ttl;
    }

    private function getCacheKey($query)
    {
        $sql = Str::replaceArray('?', $query->getBindings(), $query->toSql());

        return $this->getTable() . ":" . md5($sql);
    }

    private function getStore()
    {
        return config('cache.model.driver', config('cache.default'));
    }
}
