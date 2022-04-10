<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;

class AdminController {

    /**
     * @throws FileNotFoundException
     */
    public function getView(Filesystem $filesystem) {
        return $filesystem->get(public_path('/vendor/oxygen/index.html'));
    }

}
