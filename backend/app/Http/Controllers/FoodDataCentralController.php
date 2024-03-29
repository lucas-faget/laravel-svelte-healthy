<?php

namespace App\Http\Controllers;

use App\Models\Product;
use GuzzleHttp\Client;
use App\Http\Controllers\GoogleApiController;

class FoodDataCentralController extends Controller
{
    protected $client;
    protected static $apiKey;
    protected static $pageSize = 12;
    protected static $enableGoogleImageApi = false;

    public function __construct()
    {
        self::$apiKey = env('FDC_API_KEY');
        
        $this->client = new Client([
            'base_uri' => 'https://api.nal.usda.gov/fdc/v1/',
            'verify' => false,
        ]);

        header("Access-Control-Allow-Origin: http://localhost:5173");
        header("Access-Control-Allow-Methods: GET");
        header("Access-Control-Allow-Headers: Content-Type");
    }

    public function foodSearch(string $searchQuery, int $pageNumber = 1)
    {
        $response = $this->client->request('GET', 'foods/search', [
            'query' => [
                'api_key'    => self::$apiKey,
                'query'      => $searchQuery,
                'pageSize'   => self::$pageSize,
                'pageNumber' => $pageNumber,
                'dataType'   => 'Branded',
            ],
        ]);

        $apiResult = json_decode($response->getBody()->getContents(), true);

        $products = collect($apiResult['foods'])->map(function ($apiFood) {
            $product = new Product([
                'api_id' => $apiFood['fdcId'] ?? null,
                'name'   => $apiFood['description'] ? strtolower($apiFood['description']) : null,
                'image'  => null,
                'brand'  => $apiFood['brandName'] ?? ($apiFood['brandOwner'] ?? null),
            ]);

            if (self::$enableGoogleImageApi) {
                $googleApiController = new GoogleApiController();
                $query = $product->brand ? "$product->brand $product->name" : $product->name;
                $googleImage = $googleApiController->getGoogleImage($query);
                $product->image = $googleImage;
            }

            return $product;
        });
        
        return response()->json([
            'products'   => $products,
            'pageCount' => $apiResult['totalPages'],
        ]);
    }

    public function food(string $id)
    {
        $response = $this->client->request('GET', "food/$id", [
            'query' => [
                'api_key' => self::$apiKey,
            ]
        ]);

        $apiFood = json_decode($response->getBody()->getContents(), true);


        $product = new Product([
            'api_id'            => $apiFood['fdcId'] ?? null,
            'name'              => $apiFood['description'] ? strtolower($apiFood['description']) : null,
            'image'             => null,
            'country'           => $apiFood['marketCountry'] ?? null,
            'brand'             => $apiFood['brandName'] ?? ($apiFood['brandOwner'] ?? null),
            'description'       => $apiFood['description'] ? strtolower($apiFood['description']) : null,
            'category'          => null,
            'tags'              => $apiFood['brandedFoodCategory'] ? str_replace(', ', ',', $apiFood['brandedFoodCategory']) : null,
            'ingredients'       => $apiFood['ingredients'] ? str_replace(', ', ',', rtrim(preg_replace('/\([^)]+\)/', '', $apiFood['ingredients']), '.')) : null,
            'serving_size_unit' => $apiFood['servingSizeUnit'] ? strtolower($apiFood['servingSizeUnit']) : null,
            'serving_size'      => $apiFood['servingSize'] ?? null,
            'calories'          => $apiFood['labelNutrients']['calories']['value'] ?? 0,
            'fat'               => $apiFood['labelNutrients']['fat']['value'] ?? 0,
            'carbohydrates'     => $apiFood['labelNutrients']['carbohydrates']['value'] ?? 0,
            'protein'           => $apiFood['labelNutrients']['protein']['value'] ?? 0,
        ]);

        if (self::$enableGoogleImageApi) {
            $googleApiController = new GoogleApiController();
            $query = $product->brand ? "$product->brand $product->name" : $product->name;
            $googleImage = $googleApiController->getGoogleImage($query);
            $product->image = $googleImage;
        }

        return response()->json($product);
    }
}
