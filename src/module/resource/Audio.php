<?php

/**
 * Attobox Framework / Module Resource
 * Resource Extension
 * 
 * Audio
 * 
 * temporary support: mp3
 */

namespace Cgy\module\resource;

use Cgy\module\Resource;
use Cgy\module\resource\Stream;

class Audio extends Resource
{
    /**
     * @override getContent
     * @return null
     */
    protected function getContent(...$args)
    {
        return null;
    }

    /**
     * @override export
     * export audio using Stream
     * @return void exit
     */
    public function export($params = [])
    {
        $stream = Stream::create($this->realPath);
        $stream->init();
        exit;
    }

}