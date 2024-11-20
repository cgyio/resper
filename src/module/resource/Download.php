<?php
/**
 * cgyio/resper 资源输出类
 * 不支持的资源格式 下载
 * 
 * unsupported file, to download
 * mime = application/octet-stream
 */

namespace Cgy\module\resource;

use Cgy\module\Resource;
use Cgy\Response;
use Cgy\module\resource\Stream;

class Download extends Resource
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
     * export video using Stream
     * @return void exit
     */
    public function export($params = [])
    {
        $stream = Stream::create($this->realPath);
        $stream->down();
        exit;
    }
}