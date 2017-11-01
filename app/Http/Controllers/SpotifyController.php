<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Validator;
use Response;
use Cookie;
use Redirect;
use App\Spotifyresponse;

use Illuminate\Http\Request;

class SpotifyController extends Controller 
{

    private $client_id = 'b6ab1b85736547fba91e2cb8aa16ad2e'; // Your client id
    private $client_secret = '9fb2e5df16fb4ce898e2df27ccc883df'; // Your secret
    private $redirect_uri = 'http://localhost:8888/api/callback'; // Your redirect uri
    private $stateKey = 'spotify_auth_state';
    private $scope = 'user-read-private user-read-email';

    private function generateRandomString($length) 
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';

        for ($i = 0; $i < $length; $i++) 
        {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public function login() 
    {
        $state = $this->generateRandomString(16);
        Cookie::queue($this->stateKey, $state);

        return Redirect::away('https://accounts.spotify.com/authorize?client_id='
                               .$this->client_id.'&response_type=code'.'&scope='.$this->scope
                               .'&redirect_uri='.$this->redirect_uri.'&state='.$state, 302
                            );

    }

    public function callback(Request $request) 
    {
        $code = $request->query('code');
        $state = $request->query('state');

        $storedState = $request->cookie($this->stateKey);

        if ( !empty($state) ) 
        {
            $headers = array('Authorization: Basic '. base64_encode($this->client_id.':'.$this->client_secret));

            $_POST['code'] = $code;
            $_POST['redirect_uri'] = $this->redirect_uri;
            $_POST['grant_type'] = 'authorization_code';

            $url = 'https://accounts.spotify.com/api/token';

            $fields = array(
                'code' => urlencode($_POST['code']),
                'redirect_uri' => urlencode($_POST['redirect_uri']),
                'grant_type' => urlencode($_POST['grant_type']),
            );
            $fields_string = '';

            foreach($fields as $key => $value) 
            { 
                $fields_string .= $key.'='.$value.'&'; 
            }
            rtrim($fields_string, '&');
            
            //open connection
            $ch = curl_init();
            
            //set the url, number of POST vars, POST data
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch,CURLOPT_URL, $url);
            curl_setopt($ch,CURLOPT_POST, count($fields));
            curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            //execute post
            $result = curl_exec($ch);

            //close connection
            curl_close($ch);

            return Response::json($result);
        }
    }
}
