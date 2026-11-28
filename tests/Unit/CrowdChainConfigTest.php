<?php

it('loads business limits from the decision log', function () {
    $limits = require __DIR__.'/../../config/crowdchain.php';

    expect($limits['limits']['project_stages'])->toBe(['min' => 1, 'max' => 5])
        ->and($limits['limits']['weekly_weeks'])->toBe(['min' => 4, 'max' => 12])
        ->and($limits['limits']['active_campaigns'])->toBe(['project' => 2, 'weekly' => 1])
        ->and($limits['uploads']['max_kb'])->toBe(5120);
})->skip(fn () => ! function_exists('env'), 'needs Laravel helpers');
