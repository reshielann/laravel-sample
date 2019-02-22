<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use Validator;

use App\Models\Components\UserType;

class UserTypeController extends Controller
{
    public function __construct()
    {
        $this->guarded_roles = [1, 2, 3];
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('dashboard.users.roles')
            ->withTitle('User Management - Roles');
    }

    private function validator(Request $request)
    {
        $validator = Validator::make($request->except('_token'), [
            'id' => 'required',
            'type_name' => 'required|unique:user_type'
        ]);
        
        return $validator;
    }

    public function all()
    {
        $roles = UserType::all();

        $arr = [];
        foreach ($roles as $role) {
            $options = [
                '<a href="javascript:void(0)" class="btn btn-sm btn-info btn-edit-role"
                 data-id="'.$role->id.'">Edit</a>',
                '<a href="javascript:void(0)" class="btn btn-sm btn-danger btn-delete-role"
                  data-id="'.$role->id.'">
                    Delete
                </a>'
            ];

            $arr[] = [
                $role->type_name,
                join(' ', $options)
            ];
        }

        return [
            'data' => $arr
        ];
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = $this->validator($request);
        if ($validator->fails()) {
            return ['success' => 0, 'msg' => 'Validation failed.', 'errors' => $validator->errors()];
        }
        
        $user_type = new UserType;
        $user_type->type_name = Input::get('type_name');
        if ($user_type->save()) {
            return ['success' => 1, 'id' => $user_type->id, 'msg' => 'Successfully added new user type.'];
        }
        return ['success' => 0, 'msg' => 'Something went wrong while trying to save a new user type.'];
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \App\Models\Components\UserType
     */
    public function show(UserType $role)
    {
        return $role;
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $validator = $this->validator($request);
        if ($validator->fails()) {
            return ['success' => 0, 'msg' => 'No changes has been made.', 'errors' => $validator->errors()];
        }

        $id = Input::get('id');
        
        $user_type = UserType::find($id);
        $old_name = $user_type->type_name;
        $user_type->type_name = Input::get('type_name');
      

        if (in_array($user_type->id, $this->guarded_roles)) {
            return ['success' => 0, 'msg' => $old_name.' is a default role. You cannot update the '
                .$old_name.' role.'];
        }

        if ($user_type->save()) {
            return ['success' => 1, 'msg' => 'Updated successfully.'];
        }
        return ['success' => 0, 'msg' => 'Something went wrong while trying to update a user type.'];
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Components\UserType  $role
     * @return \Illuminate\Http\Response
     */
    public function destroy(UserType $role)
    {
        if (in_array($role->id, $this->guarded_roles)) {
            return [
                'success' => 0,
                'msg' => $role->type_name.' is a default role. You cannot delete the '.$role->type_name.' role.'
            ];
        }

        if ($role->delete()) {
            return ['success' => 1, 'msg' => 'Successfully deleted.'];
        }
        
        return ['success' => 0, 'msg' => 'Something went wrong while trying to delete a user type.'];
    }
}
