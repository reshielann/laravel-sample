<?php

namespace App\Models\Components;

use DB;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Scout\Searchable;

use Laravel\Cashier\Billable;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use App\SubscriptionPackage;

use Stripe\Customer as StripeCustomer;

class Users extends Authenticatable
{
    use Notifiable;
    use Searchable;
    use Billable;
    
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = "users";

    protected $guarded = ['id'];
    protected $hidden = ['password', 'paypal_access_token'];
    protected $dates = ['created_at', 'updated_at', 'paypal_token_expires_at'];

    /**
     * Get the index name for the model.
     *
     * @return string
     */
    public function searchableAs()
    {
        return 'users_index';
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array
     */
    public function toSearchableArray()
    {
        $array = $this->toArray();

        return $array;
    }

    /**
     * Define mutator for User's firtname
     */
    public function setFirstnameAttribute($value)
    {
        $this->attributes['firstname'] = titleCase($value);
    }


    /**
     * Define mutator for User's lastname
     */
    public function setLastnameAttribute($value)
    {
        $this->attributes['lastname'] = titleCase($value);
    }

    /**
     * Check user id and token_code
     *
     * @return integer
     */
    public function checkUserToken($user_id, $token_code)
    {
        $count = DB::table('users')
            ->select(DB::raw("*"))
            ->where('users.id', "=", $user_id)
            ->where('users.token_code', "=", $token_code)
            ->count();
        //dd($count->toSql(), $count->getBindings()); //complete display with passed values

        return $count;
    }

    /**
     * Check if email exists in the database
     *
     * @return integer
     */
    public function checkUserEmail($email)
    {
        $count = DB::table('users')
            ->select(DB::raw("*"))
            ->where('users.email_address', "=", $email)
            ->count();
        //dd($count->toSql(), $count->getBindings()); //complete display with passed values

        return $count;
    }

    
    public function role()
    {
        return $this->belongsTo(UserType::class, 'user_type');
    }

    public function isAdmin()
    {
        return $this->user_type == 3 ? true: false;
    }

    public function isBuyer()
    {
        return $this->user_type == 2 ? true: false;
    }

    public function isSeller()
    {
        return $this->user_type == 1 ? true: false;
    }

    /**
     * Returns the role of a user (admin, seller, buyer)
     *
     * @return string
     */
    public function userType()
    {
        return $this->role->type_name;
    }

    /**
     * Returns the offers claimed by this user(buyer)
     */
    public function claimedOffers()
    {
        return $this->hasMany(ClaimCodes::class, 'buyer_id');
    }

    /**
     * Returns the offers by this user (seller)
     *
     * @return App\Models\Components\Offers
     */
    public function offers()
    {
        return $this->hasMany(Offers::class, 'seller_id');
    }

    // alias for claimedOffers
    public function claimCodes()
    {
        return $this->claimedOffers();
    }

    /**
     * Returns the user's full name
     *
     * @return string
     */
    public function fullName()
    {
        return titleCase($this->firstname." ".$this->lastname);
    }

    public function emailTemplates()
    {
        return $this->hasMany(EmailTemplate::class, 'user_id');
    }

    public function products()
    {
        return $this->hasMany(Products::class, 'seller_id');
    }

    public function templates()
    {
        return $this->hasMany(Template::class, 'user_id');
    }

    public function marketplaces()
    {
        return $this->belongsToMany(Marketplace::class, 'seller_marketplace', 'user_id', 'marketplace_id')
            ->withPivot('marketplace_seller_id');
    }

    public function templatesWithAdmin()
    {
        $admin = $this->where('user_type', 3)->first();
        return Template::whereIn('user_id', [$this->id, $admin->id]);
    }
    public function emailList()
    {
        return $this->hasMany(EmailList::class, 'seller_id');
    }

    /**
     * Identifies the payment gateway used by the user for his current subscription
     *
     * @return string|bool
     */
    public function paymentGateway()
    {
        $subscription = $this->subscription('main');

        if (is_null($subscription)) {
            return false;
        }

        if (isset($subscription->paypal_response)) {
            return 'paypal';
        }

        if (isset($subscription->stripe_id)) {
            return 'stripe';
        }

        if (isset($subscription->authorize_payment_id) || $subscription->is_manual) {
            return 'authorize';
        }
    }

    public function getNextInvoiceDate()
    {
        $subscription = $this->subscription('main');

        if (!is_null($subscription)) {
            $upcomingInvoice = $this->upcomingInvoice();
            if (!is_null($upcomingInvoice)) {
                return Carbon::createFromTimestampUTC($upcomingInvoice->date, config('app.timezone'))->toDateTimeString();
            }
        }

        return false;
    }

    public function downgradeRequests()
    {
        return $this->hasMany('App\DowngradeRequest', 'user_id');
    }

    public function termPeriod()
    {
        /** @TODO return billing occurence and subscription period left from 6 months to 1 year */
        return false;
    }

    public function subscriptionPackage()
    {
        if ($this->subscribed('main')) {
            $subscription = $this->subscription('main');
            $package = SubscriptionPackage::where('plan_id', getGenericPlanId($subscription->stripe_plan))->firstOrFail();

            return $package;
        }

        return false;
    }

    public function payments()
    {
        return $this->hasMany('App\UserPayment', 'user_id');
    }
    
    public function getUserSubscriptionPlan($user_id){
        $subscription = DB::table('subscriptions')
        ->select(DB::raw("*"))
        ->where('subscriptions.user_id', "=", $user_id)
        ->orderBy('subscriptions.created_at','DESC')
        ->first();
        return $subscription;
    }

    /**
     * Determine whether a user can access the Master List 
     *
     * @return bool
     */
    public function canUseMasterList()
    {
        if ($this->isAdmin()) {
            return true;
        }
        
        if (!$this->subscribed('main')) {
            return false;
        }

        $package = $this->subscriptionPackage();

        return (bool) $package->has_master_email_list ?: false;
    }

}