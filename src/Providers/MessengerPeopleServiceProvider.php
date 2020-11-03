<?php

namespace BotMan\Drivers\MessengerPeople\Providers;

use BotMan\BotMan\Drivers\DriverManager;
use BotMan\Drivers\MessengerPeople\MessengerPeopleDriver;
use BotMan\Studio\Providers\StudioServiceProvider;
use Illuminate\Support\ServiceProvider;

class MessengerPeopleServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        if (!$this->isRunningInBotManStudio()) {
            $this->loadDrivers();

            $this->publishes([
                __DIR__ . '/../../stubs/messengerpeople.php' => config_path('botman/messengerpeople.php'),
            ]);

            $this->mergeConfigFrom(__DIR__ . '/../../stubs/messengerpeople.php', 'botman.messengerpeople');
        }
    }

    /**
     * Load BotMan drivers.
     */
    protected function loadDrivers()
    {
        DriverManager::loadDriver(MessengerPeopleDriver::class);
    }

    /**
     * @return bool
     */
    protected function isRunningInBotManStudio()
    {
        return class_exists(StudioServiceProvider::class);
    }
}
