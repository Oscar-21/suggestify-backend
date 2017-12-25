<?php

namespace App\Http\Controllers;

use Response;
use Cookie;
use Redirect;
use Illuminate\Http\Request;

use App\User;


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
        $this->access_token = 'access_token';

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
            $result = curl_exec($ch);
            $access_token = json_decode($result)->access_token;
            $refresh_token = json_decode($result)->refresh_token;
            //close connection
            curl_close($ch);

            /**
             * Sign Up / Login user
             */
            $headers = array('Authorization: Bearer '.$access_token);

            $url = 'https://api.spotify.com/v1/me';

            //open connection
            $getUser = curl_init();
            curl_setopt($getUser, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($getUser,CURLOPT_URL, $url);
            curl_setopt($getUser, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($getUser);

            $email = json_decode($result)->email;
            $display_name = json_decode($result)->display_name;
            $url = json_decode($result)->href;
            $id = json_decode($result)->id;
            $avatar = json_decode($result)->images[0]->url;
            $uri = json_decode($result)->uri;
            //close connection
            curl_close($getUser);

            $check = User::where('email', $email)->first();

            if (empty($check)) {
                $user = new User;
                $user->email = $email;
                $user->display_name = $display_name;
                $user->url = $url;
                $user->spotify_id = $id;
                $user->avatar = $avatar;
                $user->uri = $uri;
                $user->access_token = $access_token;
                $user->refresh_token = $refresh_token;
                $success = $user->save();

                if (!$success) {
                    return Response::json([ 'error' => 'Account not created' ]);
                } 
            }
            $cookie = cookie('token', $this->generateRandomString(16), 60);
            return Redirect::away('http://localhost:8080?email='.$email)->withCookie($cookie);
            
        }
    }
}
