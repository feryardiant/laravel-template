<?php

declare(strict_types=1);

use App\Livewire\Ui\Autocomplete;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::livewire('autocomplete', Autocomplete::class)->name('ui.autocomplete');

Route::get('autocomplete/search', function (Request $request) {
    $query = trim((string) $request->query('q', ''));

    $states = [
        'Alabama',
        'Alaska',
        'Arizona',
        'Arkansas',
        'California',
        'Colorado',
        'Connecticut',
        'Delaware',
        'Florida',
        'Georgia',
        'Hawaii',
        'Idaho',
        'Illinois',
        'Indiana',
        'Iowa',
        'Kansas',
        'Kentucky',
        'Louisiana',
        'Maine',
        'Maryland',
        'Massachusetts',
        'Michigan',
        'Minnesota',
        'Mississippi',
        'Missouri',
        'Montana',
        'Nebraska',
        'Nevada',
        'New Hampshire',
        'New Jersey',
        'New Mexico',
        'New York',
        'North Carolina',
        'North Dakota',
        'Ohio',
        'Oklahoma',
        'Oregon',
        'Pennsylvania',
        'Rhode Island',
        'South Carolina',
        'South Dakota',
        'Tennessee',
        'Texas',
        'Utah',
        'Vermont',
        'Virginia',
        'Washington',
        'West Virginia',
        'Wisconsin',
        'Wyoming',
    ];

    if (strtolower($query) === 'slow') {
        usleep(700_000);
    }

    $results = array_values(array_filter(
        $states,
        fn (string $state): bool => $query === '' || str_contains(strtolower($state), strtolower($query)),
    ));

    return response()->json($results);
})->name('ui.autocomplete.search');
