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

    } 

    /**
     * cookie
     */
    private function makeCookie($length) {
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
        $state = $this->makeCookie(16);
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

	/** 
         * if user accepted your application request 
         * code and state will be returned as
         * query parameters
         *
         *  For ex:
         *  http://YourRedirectURI/callback?code=FOOBAR&state=profile:activity
         */
        $code = $request->query('code');
        $state = $request->query('state');

        if ( !empty($state) ) {

	    /**
	     *
             * When the authorization code has been received, 
             * you will need to exchange it with an access 
             * token by making a POST request to the Spotify 
             * Accounts service, this time to its /api/token endpoint:
             *
             * 
              curl -H "Authorization: Basic ZjM...zE=" \
                -d grant_type=authorization_code \
                -d code=MQCbtKe...44KN \
                -d redirect_uri=https%3A%2F%2Fwww.foo.com%2Fauth \
                https://accounts.spotify.com/api/token
            * 
	    */

	    /**
		header must have be in this format: 
                Authorization: Basic <base64 encoded client_id:client_secret> 
             */
            $headers = array('Authorization: Basic '. base64_encode($this->client_id.':'.$this->client_secret));

	    // url that we will POST data too	
            $url = 'https://accounts.spotify.com/api/token';

            /** 
             * let's start filling the request body of our POST request
            */		
            $fields = array(
                'code' => urlencode($code),
                'redirect_uri' => urlencode($this->redirect_uri),
                'grant_type' => urlencode('authorization_code'),
            );

            /** 
             * We need to  
             Tranlate this: 

	     $fields =	[ 
		          'code' => dshjdh
		          'redirect_uri' => 'http://redirect'
		          'grant_type' => 'auth'
		        ];

             To this:
              $fields_string = 'code=dhsjdh&redirect_uri=http://redirect&grantype=auth'

            so we can: 
                POST https://accounts.spotify.com/api/token?code=dhsjdh&redirect_uri=http://redirect&grantype=auth
            */

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
            
            $userInfo =  curl_exec($currentUserData);
            $authUser = json_decode($userInfo);
            $email = $authUser->email;
	    $check = User::where('email', $email)->first();

            if ( empty($check) ) {
                $user = new User();
                $user->email = $email;
	        $user->save();
                $id = $user->id;
                $redis = Redis::connection();
                $redis->set('user:access_token:'.$id, $access_token);
                $redis->set('user:refresh_token:'.$id, $refresh_token);
            }

            curl_close($currentUserData);
            //return Response::json(json_decode($userInfo));

            
          return Redirect::away('https://suggestify.io/about?access_token='.$access_token.'&refresh_token='.$refresh_token.'&user='.str_replace(" ", "",str_replace("\n","",$userInfo)));
        }
    }
    private function extractAccess($string) {
        $stripBracketOne = str_replace("{", "",$string);
        $stripBracketTwo = str_replace("}", "",$stripBracketOne);
        $stripQuotes = str_replace("\"", "",$stripBracketTwo);
        $stripColon = str_replace(":", "",$stripQuotes);
        $stripComma = str_replace(",", "",$stripColon);
        $stripAccessKey = str_replace("access_token", "",$stripComma);
        $pos = stripos($stripAccessKey, "token"); 
        return substr_replace($stripAccessKey, "", $pos);
    }

    private function extractRefresh($string) {
        $stripBracketOne = str_replace("{", "",$string);
        $stripBracketTwo = str_replace("}", "",$stripBracketOne);
        $stripQuotes = str_replace("\"", "",$stripBracketTwo);
        $stripColon = str_replace(":", "",$stripQuotes);
        $stripComma = str_replace(",", "",$stripColon);
        $pos = stripos($stripComma, "refresh_token"); 
        $stripFront = substr_replace($stripComma, "", 0,$pos);
        $pos = stripos($stripFront, "scope"); 
        $stripEnd = substr_replace($stripFront, "", $pos);
        return str_replace("refresh_token", "", $stripEnd);

    }
    public function foobar() {
        $redis = Redis::connection();
        $redis->set('foobar', 'dodo');
        $check = $redis->get('foobar');
    }
}
