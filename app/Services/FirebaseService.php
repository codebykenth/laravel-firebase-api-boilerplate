<?php

namespace App\Services;

use Kreait\Firebase\Factory;

class FirebaseService
{
    protected $firestore;

    public function __construct()
    {
        $firebase = (new Factory)->withServiceAccount(base_path(env('FIREBASE_CREDENTIALS')));

        $this->firestore = $firebase->createFirestore()->database();
    }

    public function getFirestore()
    {
        return $this->firestore;
    }
}