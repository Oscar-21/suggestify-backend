<?php

namespace App\Http\Controllers;

//use Purifier;
//use Hash;
//use Auth;
//use JWTAuth;
use Illuminate\Support\Facades\Validator;
use Response;
use Cookie;
use Redirect;

// Models
use App\Spotifyresponse;

use Illuminate\Http\Request;

class SpotifyController extends Controller {

    /**
     * Constructor
     */
    public function __construct() {
        // auth
        $this->client_id = config('services.spotify.clientID'); // Your client secret
        $this->client_secret = config('services.spotify.clientSecret'); // Your secret
        $this->redirect_uri = config('services.spotify.redirectURI'); // Your redirect uri
        $this->stateKey = config('services.spotify.stateKey');
        $this->scope = config('services.spotify.scope');

        // api
//        $this->access_token = 'access_token';

    } 


    /**
     * Temporary Token generator
     */
    private function generateRandomString($length) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }


    /**
     * Login 
     */
    public function login() {
        $state = $this->generateRandomString(16);
        Cookie::queue($this->stateKey, $state);

        return Redirect::away('https://accounts.spotify.com/authorize?client_id='
                            .$this->client_id.'&response_type=code'.'&scope='.$this->scope
                            .'&redirect_uri='.$this->redirect_uri.'&state='.$state, 302
                );
    }


    /**
     * Callback 
     */
    public function callback(Request $request) {
        $code = $request->query('code');
        $state = $request->query('state');

        $storedState = $request->cookie($this->stateKey);

        if ( !empty($state) ) {
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

            foreach($fields as $key => $value) { 
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
            $extractAccessToken = curl_exec($ch);
            curl_close($ch);

            $extractRefreshToken = $extractAccessToken;

            // extract access_token
            $stripBracketOne = str_replace("{", "",$extractAccessToken);
            $stripBracketTwo = str_replace("}", "",$stripBracketOne);
            $stripQuotes = str_replace("\"", "",$stripBracketTwo);
            $stripColon = str_replace(":", "",$stripQuotes);
            $stripComma = str_replace(",", "",$stripColon);
            $stripAccessKey = str_replace("access_token", "",$stripComma);
            $pos = stripos($stripAccessKey, "token"); 
            $access_token = substr_replace($stripAccessKey, "", $pos);

            // extract refresh_token
            $stripBracketOne = str_replace("{", "",$extractRefreshToken);
            $stripBracketTwo = str_replace("}", "",$stripBracketOne);
            $stripQuotes = str_replace("\"", "",$stripBracketTwo);
            $stripColon = str_replace(":", "",$stripQuotes);
            $stripComma = str_replace(",", "",$stripColon);
            $pos = stripos($stripComma, "refresh_token"); 
            $stripFront = substr_replace($stripComma, "", 0,$pos);
            $pos = stripos($stripFront, "scope"); 
            $stripEnd = substr_replace($stripFront, "", $pos);
            $refresh_token = str_replace("refresh_token", "", $stripEnd);

            return Redirect::away('http://ec2-52-42-142-157.us-west-2.compute.amazonaws.com?access_token='.$access_token.'&refresh_token='.$refresh_token);
            
        }
    }
}
