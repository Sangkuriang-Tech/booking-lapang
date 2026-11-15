<?php

namespace App\Http\Controllers\Public;

use App\Actions\Landing\GetLandingPageData;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function index(Request $request, GetLandingPageData $getLandingPageData): Response
    {
        $data = $getLandingPageData->execute();
        $baseUrl = rtrim($request->getSchemeAndHttpHost(), '/');

        return Inertia::render('Home', [
            'sports' => $data['sports'],
            'cities' => $data['cities'],
            'featuredVenues' => $data['featuredVenues'],
            'plans' => $data['plans'],
            'stats' => $data['stats'],
            'seo' => [
                'canonical' => $baseUrl.'/',
                'siteUrl' => $baseUrl,
                'siteName' => config('app.name', 'ArenaKita'),
            ],
        ]);
    }
}
