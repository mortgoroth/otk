<?php

    namespace App\Providers;

    use Illuminate\Support\ServiceProvider;

    class AppServiceProvider extends ServiceProvider {
        /**
         * Register any application services.
         */
        public function register ():void {

            $this->app->bind(\App\Contracts\LdapProvider::class, function () {
                return match (config('services.ldap.schema')) {
                    'OpenLDAP' => new \App\Services\Ldap\OpenLdapProvider(),
                    default => new \App\Services\Ldap\ActiveDirectoryProvider(),
                };
            });
            $this->app->singleton(\App\Services\Otk\OtkApiService::class, function ($app) {
                return new \App\Services\Otk\OtkApiService();
            });

        }

        /**
         * Bootstrap any application services.
         */
        public function boot ():void {
            //
        }
    }
