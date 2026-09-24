<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Public\SiteResource;
use App\Services\Public\SitePayload;

class SiteController extends Controller
{
    public function __invoke(SitePayload $site): SiteResource
    {
        return new SiteResource($site->build());
    }
}
