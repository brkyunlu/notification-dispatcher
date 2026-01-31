<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,
    App\Providers\ModuleServiceProvider::class,
    App\Modules\Observability\Providers\TracingServiceProvider::class,
];
