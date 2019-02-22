<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;

use Auth;
use Validator;
use JavaScript;
use DataTables;
use Mail;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

use App\Http\Requests\StoreUser;
use App\Libraries\PaypalMass;
use App\Models\Components\Users;
use App\Models\Components\Offers;
use App\Models\Components\UserType;
use App\Models\Components\SellerMarketplace;
use App\Models\Components\Marketplace;
use App\Mail\SendEmailVerification;
use App\Mail\PasswordChanged;
use App\User;
use App\Traits\SubscribesUsers;
use App\SubscriptionPackage;
use App\DowngradeRequest;

class UsersController extends Controller
{
    use SubscribesUsers;

    function __construct()
    {
        $this->setAnetPlansConfig();
    }

    public function index()
    {
        $roles = UserType::all();
        return view('dashboard.users.index')->withRoles($roles)
            ->withTitle("Users Management");
    }

    public function show(Users $user)
    {
        return $user;
    }

    public function deactivate(Users $user)
    {
        if ($user->status == 0 or $user->status == 2) {
            $action = "activate";
            if (auth()->user()->user_type == 3 && !$user->registration_status) {
                // when admin activates an account, it should also approve the account automatically
                $user->registration_status = 1;
            }
            $user->status = 1;
        } else {
            $action = "deactivate";
            $user->status = 0;
        }

        if ($user->save()) {
            return ['success' => 1, 'msg' => "$user->firstname has been {$action}d"];
        }
        return ['success' => 0, 'msg' => "Opps, something went wrong. We're unable to $action a user right now."];
    }
    
    public function registrationApprove(Users $user)
    {
        $action = "Approve";
        $user->registration_status = 1;

        if ($user->save()) {
            //send email verification

            $mail = new SendEmailVerification($user);
            Mail::to($user->email_address)->send($mail);
            
            return ['success' => 1, 'msg' => "$user->firstname has been {$action}d"];
        }
        return ['success' => 0, 'msg' => "Opps, something went wrong. We're unable to $action a user right now."];
    }

    public function store(StoreUser $request)
    {
        if (auth()->user()->user_type != 3) {
            return response(403, 'Unauthorize access.');
        }

        $request['password'] = bcrypt($request['password']);
        $request['token_code'] = str_random(15);

        $input = $request->except('_token', 'confirm_password');
        $input['api_token'] = str_random(60);
        
        if ($input['user_type'] != 1) {
            $input['registration_status'] = 1;
        }
        
        $user = new Users($input);
       
        if ($user->save()) {
            if ($input['user_type'] != 1) {
                $mail = new SendEmailVerification($user);
                Mail::to($user->email_address)->send($mail);
            }
            return ['success' => 1, 'msg' => 'User created successfully.'];
        }
        return ['success' => 0, 'msg' => "Opps, something went wrong. We're unable to create a user right now."];
    }

    public function update(StoreUser $request)
    {
        $user = Users::find($request->id);
        if (!empty($user)) {
            $user->firstname = $request->firstname;
            $user->lastname = $request->lastname;
            $user->email_address = $request->email_address;
            $user->user_type = $request->user_type;
            $user->country = $request->country;

            $user->username = $request->username;

            if ($request->has('password') && $request->password != "") {
                $user->password = bcrypt($request->password);
                $user->new_password = $request->password;

                try {
                    $mail = new PasswordChanged($user);
                    // Notify the user for the new password, and show them the new password so they can change it later
                    Mail::to($user->email_address)->send($mail);
                    unset($user->new_password);
                } catch (\Exception $e) {
                    \Log::error("Failed sending PasswordChanged notification email to {$user->email_address}");
                }
            }

            if ($user->save()) {
                return [
                    'success' => 1,
                    'msg' => 'User details successfully updated.'
                ];
            }
            return [
                'success' => 0,
                'msg' => "Opps, something went wrong while trying to update user details."
            ];
        }

        return [
            'success' => 0,
            'msg' => "Sorry, we can't find the user you specified."
        ];
    }

    public function getUsers()
    {
        $builder = Users::where('email_address', '<>', auth()->user()->email_address)->with('role');

        $excess = [
            'password', 'token_code', 'api_token', 'remember_token', 'created_at', 'updated_at'
        ];

        return DataTables::of($builder)
                ->addColumn('name', function (Users $user) {
                    return $user->fullName();
                })
                ->addColumn('role', function (Users $user) {
                    return getUserRoleHtml($user->role);
                })
                ->addColumn('membership_status', function (Users $user) {
                    switch ($user->status) {
                        case 0:
                            $status = '<label class="label label-warning">Inactive</label>';
                            break;
                        case 1:
                            $status = '<label class="label label-success">Active</label>';
                            break;
                        case 2:
                            $status = '<label class="label label-danger">Suspended</label>';
                            break;
                    }

                    return $status;
                })
                ->addColumn('registration_status', function (Users $user) {
                    switch ($user->registration_status) {
                        case 0:
                            $status = '<label class="label label-warning">Pending</label>';
                            break;
                        case 1:
                            $status = '<label class="label label-success">Approved</label>';
                            break;
                    }
                
                    return $status;
                })
                ->filterColumn('user_type', function ($query, $keyword) {
                    $query->whereRaw(
                        "firstname = (SELECT id FROM user_type WHERE LOWER(type_name) like LOWER('%".$keyword."%'))" 
                    );
                })
                ->filterColumn('status', function ($query, $keyword) {
                    $keyword = strtolower($keyword);
                    $status = 1;
                    
                    if ($keyword == 'active') {
                        $status = 1;
                    } elseif ($keyword == 'suspended') {
                        $status = 2;
                    } elseif ($keyword == 'inactive') {
                        $status = 0;
                    }

                    $query->whereRaw("status = $status");
                })
                ->addColumn('action', 'datatables.users.buttons.action')
                ->escapeColumns($excess)
                ->rawColumns([9, 10, 11])
                ->make(true);
    }

    public function find(Users $user)
    {
        return $user;
    }

    public function suspend(Users $user)
    {
        $user->status = 2;

        if ($user->save()) {
            return ['success' => 1, 'msg' => "{$user->firstname} is now suspended."];
        }

        return ['success' => 0, 'msg' => "Opps, something went wrong while trying to suspend a user."];
    }

    /**
     * Deletes a user
     *
     * @param  \App\Models\Components\Users $user
     * @return mixed
     */
    public function delete(Users $user)
    {
        $confirmed = true;

        $temp = $user;

        $response = [
            'message' => "Something went wrong while trying to delete this user with an ID {$temp->id}",
            'success' => 0
        ];

        if (!empty($user->subscription('main')) && $user->subscription('main')->valid()) {
            $response['message'] = "You cannot delete a user with an active subscription.";
            $confirmed = false;
        }

        if (auth()->user()->id == $user->id) {
            $response["message"] = "You are cannot delete yourself.";
            $confirmed = false;
        }

        if ($confirmed) {
            if ($user->delete()) {
                return [
                    'message' => "You have successfully deleted ".$user->fullName(),
                    'success' => 1
                ];
            }
        }

        return $response;
    }

    /**
     * Loads Edit Profile page
     *
     * @return \Illuminate\Http\Response
     */
    public function editProfile()
    {
        $user = Auth::user();
        
        //get marketplace profile
        $seller_marketplace = new SellerMarketplace();
        $marketplace = $seller_marketplace->getSellerMarketplace($user->id);

        $user = Users::find($user->id);
        if ($user->paymentGateway() == 'authorize') {
            $user = User::find($user->id); // use the User model intended for Authorize.net
        }

        $packages = SubscriptionPackage::orderBy('monthly_fee', 'asc')->get();
        $subscription = null;
        $currentPackage = null;

        if (count($user->subscriptions) > 0) {
            $subscription = $user->subscription('main');
            $currentPackage = $packages->where('plan_id', getGenericPlanId($subscription->stripe_plan))->first();
        }

        $data = array(
            'url' => 'update-profile/',
            'seller_marketplace' => $marketplace,
            'method' => 'POST',
            'title' => 'Edit Profile',
            'user' => $user,
            'packages' => $packages,
            'colors' => [
                'btn-success',
                'btn-info',
                'btn-warning'
            ],
            'subscription' => $subscription,
            'currentPackage' => $currentPackage
        );

        $data = $data + $user->toArray();

        return view('auth.editprofile')->with($data);
    }

    /**
     * Save profile changes in the database
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function updateProfile(Request $request)
    {
        $user_id = $request->user()->id;

            $validator = Validator::make($request->except('_token'), [
                'username' => 'required|unique:users,username,'.$user_id,
                'firstname' => 'required|regex:/^[A-Za-z\s-_]+$/',
                'lastname' => 'required|regex:/^[A-Za-z\s-_]+$/',
                'email_address' => 'required|email|unique:users,email_address,'.$user_id
            ]);
       
        if ($validator->fails()) {
            //get all the errors;
            $error = $validator->errors()->all();
            $msg = '';
            $counter = 0;
            foreach ($error as $i => $v) {
                $msg .= $v . ' ';

                $counter++;
            }
            //return redirect()->back()->withErrors($validator); //original

            return redirect()->back()->withErrors($validator)->with('error', 'Error')->withInput();
        } else {
            // $user = Users::where('id', $request->user_id)->first();
            $user = $request->user();
            $user->username= isset($request->username) ? $request->username : '';
            $user->firstname= isset($request->firstname) ? $request->firstname : '';
            $user->lastname= isset($request->lastname) ? $request->lastname : '';
            $user->email_address= isset($request->email_address) ? $request->email_address : '';
            $user->country= isset($request->country) ? $request->country : '';
            
            //if user is buyer
            if ($user->user_type == 2) {
                $user->paypal_email_address = isset($request->paypal_email_address) ? $request->paypal_email_address : '';
            }
            if ($request->password != '') {
                $user->password = bcrypt($request->password);
            }

            $user->save();
            
            //chck is user is seller
            if ($user->user_type == 1) {
                $user->paypal_client_id = $request->paypal_client_id;
                $user->paypal_secret = $request->paypal_secret;

                $user->save();

                //if seller id is not empty setup marketplace profile
                //check if marketplace profile exist
                $seller_marketplace = new SellerMarketplace();
                $marketplace_profile = $seller_marketplace->checkSellerMarketplaceProfile($user->id);
                if ($marketplace_profile > 0) {
                    //if exist, update
                    $seller_profile = SellerMarketplace::where('user_id', '=', $user->id)->get();
                    foreach ($seller_profile as $profile) {
                        $seller_marketplace = SellerMarketplace::where('id', $profile->id)->first();
                        $seller_marketplace->marketplace_seller_id = $request->seller_id[$profile->marketplace_id];
                        $seller_marketplace->save();
                    }
                } else {
                    //if not, create
                    $marketplaces = Marketplace::get();
                    foreach ($marketplaces as $marketplace) {
                        $seller_marketplace = new SellerMarketplace;
                        $seller_marketplace->user_id = $user->id;
                        $seller_marketplace->marketplace_id = $marketplace->id;
                        $seller_marketplace->marketplace_seller_id = $request->seller_id[$marketplace->id];
                        $seller_marketplace->save();
                    }
                }
            }
            
            \Session::flash('flash_message', 'Profile was successfully updated!');
            \Session::flash('alert-class', 'alert-success');
            return redirect('/edit-profile');
        }
    }

    /**
     * Gets the Paypal access token
     *
     * @return string
     */
    public function getAccessToken()
    {
        $paypal = new PaypalMass(auth()->user());
        $token = $paypal->getAccessToken();

        return $token;
    }

    /**
     * Tests the Paypal's connection to API
     * using the given credentials
     *
     * @param  \Illuminate\Http\Request $request
     * @return array
     */
    public function testAppCredentials(Request $request)
    {
        $user = auth()->user();
        $user->paypal_client_id = $request->paypal_client_id;
        $user->paypal_secret = $request->paypal_secret;

        $paypal = new PaypalMass($user);
        $token = $paypal->getAccessToken();

        if ($token !== false) {
            return [
                'success' => 1,
                'message' => 'Your Paypal App was successfully connected.'
            ];
        }
        return [
            'success' => 0,
            'message' => 'Could not connect to Paypal.
                        Make sure you entered your Client ID and Secret Key correctly.'
        ];
    }

    public function generateUrl() {
        $user = auth()->user();
        $ouc = new Offers();
        $offer_url_code = $ouc->generateOfferUrlCode(10);
        $url = url('/affiliate/'.$offer_url_code);
        
        $user_model = Users::find($user->id);
        $user_model->affiliate_page_url = $url;
        
        if($user_model->save()) {
            \Session::flash('flash_message', 'Affiliate Page URL has been created!');
            \Session::flash('alert-class', 'alert-success');
        } else {
            \Session::flash('flash_message', 'There is a problem while processing your request. Please try again later!');
            \Session::flash('alert-class', 'alert-danger');
        }
        return redirect('/edit-profile');
    }

    /**
     * Resumes the user's recently cancelled subscription
     * 
     * @return array
     */
    public function resumeSubscription(Request $request)
    {
        $user = $request->user();
        $subscription = $user->subscription('main');

        if (!$subscription->onGracePeriod()) {
            return [
                'success' => 0,
                'message' => "Unable to resume subscription that is not within grace period."
            ];
        }

        try {
            $subscription->resume();
        } catch (\Exception $e) {
            return [
                'success' => 0,
                'message' => "Somthing went wrong while trying to resume your subscription. Please try again later."
            ];
        }

        return [
            'success' => 1,
            'message' => "Your subscription was successfully resumed."
        ];
    }

    /**
     * Cancels the user's current subscription
     *
     * @return array
     */
    public function cancelSubscription(Request $request)
    {
        $user = $request->user();
        $gateway = $user->paymentGateway();

        if ($gateway != "stripe") {
            $user = User::findOrFail($request->user()->id);
            $billingDate = getNextBillingDate($user, false);
        } else {
            $invoice = $user->upcomingInvoice();
            $billingDate = Carbon::createFromTimestamp($invoice->date);
        }

        // check if subscription period is almost done
        $subscription = $user->subscription('main');
        $now = Carbon::now(config('app.timezone'));
        

        // same month and year but current date is behind by days
        // this scenario occurs when we skipped the trial period, and the user hasn't completed any payments yet
        if (justRecentlyCreated($subscription, $billingDate)) {
            // No payment has been made yet, so let's forget all the drama and just create the subscription
            if (!$subscription->is_manual) {
                $subscription->cancelNow();
            } else {
                $subscription = cancelSubscriptionManually($subscription);
            }

             return [
                'success' => 1,
                'message' => "Your subscription has been cancelled."
            ];
        }

        try {

            if ($subscription->onTrial()) {
                if (!$subscription->is_manual) {
                    $subscription->cancelNow();
                } else {
                    $subscription = cancelSubscriptionManually($subscription);
                }
            } else {
                // get full subscription period
                $lastPeriod = $this->getUserFullSubscriptionPeriod($subscription);

                if ($gateway == 'stripe') {
                    $lastPeriod = $billingDate;
                }

                $today = Carbon::Now(config('app.timezone'));

                if ($today->format('m-Y') != $lastPeriod->format('m-Y')) {
                    return [
                        'success' => 0,
                        // 'message' => "You can't cancel your subscription until ".$lastPeriod->addDays(1)->format('F d, Y')
                        'message' => "$lastPeriod You can't cancel your subscription until ".$lastPeriod->subDays(1)->format('F d, Y')
                    ];
                }

                switch ($gateway) {
                    case 'paypal':
                        $ends = Carbon::parse($subscription->ends_at)->toDateTimeString();
                        $appname = config('app.name');

                        // let's return it to true as we already set the subscription's ends_at column everytime a subscription is made with paypal
                        return [
                            'success' => 1,
                            'msg' => "Your subscription has been cancelled. Your access to $appname ends on $ends"
                        ];

                    case 'authorize':
                        $user = User::find($user->id); // switch to Authorize.net User model
                        break;
                }

                $subscription->cancel();
            }
        } catch (\Exception $e) {
            \Log::error($e);
            return [
                'success' => 0,
                'message' => "Something went wrong while trying cancel your subscription. Please try again later."
            ];
        }

        return [
            'success' => 1,
            'message' => "Your subscription has been cancelled."
        ];
    }

    /**
     * Changes the user's subscription
     *
     * @param  \Illuminate\Http\Request $request
     * @return array
     */
    public function changeSubscription(Request $request)
    {
        return $this->updateSubscription($request);
    }

    public function downgradeRequests(Request $request)
    {
        /* for TESTING hack */
        return [
            'success' => 1,
            'has_request' => 1,
            'approved' => 1,
            'message' => "Downgrade approved."
        ];

        //@TODO every 6 months and yearly not yet working

        // change of flow:

        $today = Carbon::now(config('app.timezone'));  // 2018-06-21

        $user = $request->user();
        

        $subscription = $user->subscription('main');

        // get the first and primary subscription package
        $package = SubscriptionPackage::where('plan_id', getGenericPlanId($subscription->stripe_plan))->first();

        // get start of subscription
        $startDate = Carbon::parse($subscription->created_at);  // 2018-05-30

        $subscriptionCreated = $startDate->copy();
       
        // if the subscription offers trial period, add it
        $startDate->addDays($package->trial_days); // ex oct. 6 + 7 = october 13 first payment, and next payment is nov 13

        // this month's payment date
        $thisMonthSched = Carbon::createFromDate($today->year, $today->month, $startDate->day, config('app.timezone'));

        // next month's payment date
        $nextMonthSched = $thisMonthSched->copy()->addMonth();

        $dateToAllowDowngrade = $today < $thisMonthSched ? $thisMonthSched->copy()->subDays(1) : $nextMonthSched->copy()->subDays(1);
        $minDateToAllowDowngrade = $dateToAllowDowngrade->copy()->subDays(2);


        $str_today = $today->toDateString();
        $str_date_downgrade = $dateToAllowDowngrade->toDateString();
        $str_min_date_downgrade = $minDateToAllowDowngrade->toDateString();
        $str_middle_date_downgrade = $dateToAllowDowngrade->copy()->subDays(1);

        // allow downgrade 3 days before the next payment
        if ($subscription->cancelled() || $str_today == $str_date_downgrade 
            || $str_today == $str_middle_date_downgrade || $str_today == $str_min_date_downgrade
            || ($package->trial_days > 0 && $str_today == $subscriptionCreated->toDateString())
        ) {
            return [
                'success' => 1,
                'has_request' => 1,
                'approved' => 1,
                'message' => "Downgrade approved."
            ];
        } else {
            return [
                'success' => 0,
            'message' => "Sorry you can't downgrade at this time. You'll be able to downgrade on ".$minDateToAllowDowngrade->toFormattedDateString()
                 ." until ".$dateToAllowDowngrade->toFormattedDateString()." EST"
            ];
        }


        return [
            'success' => 0,
            'message' => "Something went wrong while trying to submit a downgrade request. Please try again later."
        ];
    }
}
