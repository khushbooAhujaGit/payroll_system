<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    protected $table    = 'locations';
    protected $fillable = ['company_id', 'name', 'code', 'address', 'city', 'state', 'is_active'];

    /**
     * Fixed list offered on the Add/Edit Location dropdown — a Location
     * record IS a city (used to say where a Work from Home employee is
     * based), so there's no separate free-text city field anymore. Edit
     * this list directly to add/remove cities; it's plain PHP, not DB-driven.
     */
    const CITIES = [
        'Agra', 'Ahmedabad', 'Ajmer', 'Aligarh', 'Amritsar', 'Aurangabad',
        'Bengaluru', 'Bhopal', 'Bhubaneswar', 'Chandigarh', 'Chennai', 'Coimbatore',
        'Dehradun', 'Delhi', 'Dhanbad', 'Faridabad', 'Ghaziabad', 'Gorakhpur',
        'Guwahati', 'Gurugram', 'Gwalior', 'Hyderabad', 'Imphal', 'Indore',
        'Itanagar', 'Jabalpur', 'Jaipur', 'Jammu', 'Jamshedpur', 'Jodhpur',
        'Kanpur', 'Kochi', 'Kohima', 'Kolkata', 'Kota', 'Lucknow', 'Ludhiana',
        'Madurai', 'Mangalore', 'Meerut', 'Mumbai', 'Mysuru', 'Nagpur', 'Nashik',
        'Noida', 'Panaji', 'Patna', 'Prayagraj', 'Puducherry', 'Pune', 'Raipur',
        'Rajkot', 'Ranchi', 'Shillong', 'Shimla', 'Silchar', 'Srinagar', 'Surat',
        'Thane', 'Thiruvananthapuram', 'Tiruchirappalli', 'Udaipur', 'Vadodara',
        'Varanasi', 'Vijayawada', 'Visakhapatnam',
    ];

    public function company(): BelongsTo  { return $this->belongsTo(Company::class); }
    public function employees(): HasMany  { return $this->hasMany(Employee::class); }
}
