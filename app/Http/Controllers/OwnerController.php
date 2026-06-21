<?php

namespace App\Http\Controllers;

use App\Http\Resources\PersonalizationFull;
use App\Models\Personalization;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OwnerController extends Controller
{
    public function updatePersonalization(Request $request): PersonalizationFull|JsonResponse
    {
        $params = $request->all();
        // Both fields are optional so the app can update the logo and the brand
        // colour independently (saving one must not wipe the other). brand_color is
        // an org-controlled value that ends up inlined into an email style attribute,
        // so it is constrained to a strict #RRGGBB hex — never free-form CSS.
        $settings = [
            'photo_url' => 'sometimes|nullable|string',
            'brand_color' => 'sometimes|nullable|regex:/^#[0-9a-fA-F]{6}$/',
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        $owner = $this->getOwner();

        // Only touch the keys actually sent — a logo-only or colour-only request
        // leaves the other column as it was.
        $attributes = [];
        if (array_key_exists('photo_url', $params)) {
            $attributes['photo_url'] = $params['photo_url'];
        }
        if (array_key_exists('brand_color', $params)) {
            $attributes['brand_color'] = $params['brand_color'];
        }

        $personalization = Personalization::updateOrCreate(
            ['owner' => $owner],
            $attributes
        );

        return new PersonalizationFull($personalization);
    }
}
