<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Response;
use Purifier;

use App\User;

class UserController extends Controller {
    // public function createPlaylist() {
    //     $rules = [
    //         'name' => 'required|string',
    //     ]; 

    //     // Validate input against rules
    //     $validator = Validator::make(Purifier::clean($request->all()), $rules);

    //     if ($validator->fails()) {
    //         return Response::json(['error' => 'You must fill out all fields.']);
    //     }



    //         'public' => true,
    //         'collaborative' => true,
    // }
    public function getUser($email) {
        
    }
}
