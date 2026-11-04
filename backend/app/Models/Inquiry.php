<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Inquiry extends Model
{
    use HasUuids;

    protected $fillable = ['listing_id', 'buyer_id', 'message', 'share_contact', 'status', 'admin_notes'];

    protected $casts = ['share_contact' => 'boolean'];

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }
}
