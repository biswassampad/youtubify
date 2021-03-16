<?php namespace App\Http\Controllers;

use Storage;
use App\Track;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

class TrackStreamController extends Controller {

	/**
	 * Find track matching given id.
	 *
	 * @param int    $id
     * @oaram string $mime
	 * @return Track
	 */
	public function stream($id, $mime)
	{
		$track  = Track::findOrFail($id);
        $path   = 'application/storage/app/music/'.md5(Str::slug($track->artists[0]).'_'.Str::slug($track->album_name).'_'.Str::slug($track->name));

        $size	= Storage::size($path);
        $time	= date('r', Storage::lastModified($path));
        $fm		= Storage::getDriver()->readStream($path);
        $begin	= 0;
        $end	= $size - 1;

        if (isset($_SERVER['HTTP_RANGE']))
        {
            if (preg_match('/bytes=\h*(\d+)-(\d*)[\D.*]?/i', $_SERVER['HTTP_RANGE'], $matches))
            {
                $begin	= intval($matches[1]);
                if (!empty($matches[2]))
                {
                    $end = intval($matches[2]);
                }
            }
        }

        if (isset($_SERVER['HTTP_RANGE']))
        {
            header('HTTP/1.1 206 Partial Content');
        }
        else
        {
            header('HTTP/1.1 200 OK');
        }

        header("Content-Type: $mime");
        header('Cache-Control: public, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Accept-Ranges: bytes');
        header('Content-Length:' . (($end - $begin) + 1));
        if (isset($_SERVER['HTTP_RANGE']))
        {
            header("Content-Range: bytes $begin-$end/$size");
        }
        header("Content-Disposition: inline; filename=$track->name");
        header("Content-Transfer-Encoding: binary");
        header("Last-Modified: $time");

        $cur	= $begin;
        fseek($fm, $begin, 0);

        while(!feof($fm) && $cur <= $end && (connection_status() == 0))
        {
            print fread($fm, min(1024 * 16, ($end - $cur) + 1));
            $cur += 1024 * 16;
        }
	}
}
