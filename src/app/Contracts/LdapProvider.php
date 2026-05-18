<?php

    namespace App\Contracts;

    interface LdapProvider {
        public function findUser (string $username):?array;
    }
