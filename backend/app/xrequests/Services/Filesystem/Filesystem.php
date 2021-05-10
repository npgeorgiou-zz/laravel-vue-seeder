<?php

namespace Xrequests\Services\Filesystem;

use App\Models\File;

interface Filesystem {
    function read(File $asset);
    function write(File $asset, string $content);
    function delete(File $asset);
    function has(File $asset);
}
