<?php

namespace Xrequests\Services\Filesystem;

use App\Models\File;

class Local implements Filesystem {

    function read(File $asset) {
        return file_get_contents(pathOf($asset));
    }

    function write(File $asset, string $content) {
        file_put_contents(pathOf($asset), $content);
    }

    function delete(File $asset) {
        unlink(pathOf($asset));
    }

    function has(File $asset) {
        return file_exists(pathOf($asset));
    }
}

function pathOf(File $asset) {
    return "storage/files/$asset->name.$asset->mimetype";
}
