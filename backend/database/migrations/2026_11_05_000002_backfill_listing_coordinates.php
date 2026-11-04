<?php

use App\Services\GeoService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Listings whose town name was not recognised had no map pin; place them now. */
return new class extends Migration
{
    public function up(): void
    {
        $geo = new GeoService;
        DB::table('listings')->whereNull('latitude')->whereNotNull('city')->orderBy('id')
            ->each(function ($row) use ($geo) {
                if ($c = $geo->coordinatesFor($row->city, (string) $row->id)) {
                    DB::table('listings')->where('id', $row->id)->update(['latitude' => $c[0], 'longitude' => $c[1]]);
                }
            });
    }

    public function down(): void
    {
    }
};
