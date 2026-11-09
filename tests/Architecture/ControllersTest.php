<?php

declare(strict_types=1);

use App\Http\Controllers\Controller;

it('controllers should extend Controller class', function (): void {
    expect('App\Http\Controllers')
        ->toBeClasses()
        ->classes()
        ->toExtend(Controller::class);
});
