<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('modules')) {
            DB::table('modules')->where('slug', 'vehicle-dealership')->delete();
        }

        // A store that ran the retired model falls back to a normal retail
        // storefront. The admin can choose the multi-vendor model afterwards;
        // silently switching it on would create a marketplace with no sellers.
        if (Schema::hasTable('settings')) {
            DB::table('settings')
                ->where('key', 'store.mode')
                ->where('value', 'dealership')
                ->update(['value' => 'retail', 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // The retired model is not restored.
    }
};
