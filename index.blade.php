@extends('master')

@section('container')
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <h1>
        Users Management
      </h1>
      <ol class="breadcrumb">
        <li><a href="{{url('/dashboard')}}"><i class="fa fa-dashboard"></i> Dashboard</a></li>
        <li class="active">Users</li>
      </ol>
    </section>

    <!-- Main content -->
    <section class="content">
     
        <!-- Main row -->
        <div class="row">
            <!-- Left col -->
            <section class="col-lg-12 connectedSortable">
                <div class="box">
                    <div class="box-header with-border">
                        <a class="btn btn-info" id="btn_add_new"><i class="fa fa-plus"></i> Add new</a>
                    </div>
                    <!-- /.box-header -->
                    <div class="box-body table-responsive">
                        <table class="table table-bordered table-hover" id="tbl_users">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th style="visibility: hidden;">First Name</th>
                                    <th style="visibility: hidden;">Last Name</th>
                                    <th>Email</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th style="width: 2%">Registration Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </section>
            <!-- right col -->
        </div>
        <!-- /.row (main row) -->

    </section>
    <!-- /.content -->
   
    <!-- Modal -->
    <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form role="form" method="post" action="" id="form_users">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" id="myModalLabel">View/Edit</h4>
                    </div>
                    <div class="modal-body">  
                        <input type="hidden" name="id" id="user_id" value="new" />        
                        <input type="hidden" name="_token" value="{{ csrf_token() }}" /> 
                        <input type="hidden" id="status" name="status" value="0">
                        <div class="form-group has-feedback">
                            <label for="firstname">First Name</label>
                            <input type="text" class="form-control" name="firstname" id="firstname" required placeholder="Firstname">
                            <span class="glyphicon glyphicon-user form-control-feedback"></span>
                        </div>
                        <div class="form-group has-feedback">
                            <label for="lastname">Last Name</label>
                            <input type="text" class="form-control" name="lastname" id="lastname" required placeholder="Lastname">
                            <span class="glyphicon glyphicon-user form-control-feedback"></span>
                        </div>
                        <div class="form-group has-feedback">
                            <label for="email_address">Email</label>
                            <input type="email" class="form-control" name="email_address" id="email_address" required placeholder="Email Address">
                            <span class="glyphicon glyphicon-envelope form-control-feedback"></span>
                        </div>
                        
                        <div class="form-group">
                            <label for="user_type">Type</label>
                            <select name="user_type" id="user_type" class="form-control" required>
                                @foreach($roles as $role)
                                <option value="{{ $role->id }}">{{ $role->type_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group has-feedback">
                            <label for="username">Username</label>
                            <input type="text" class="form-control" name="username" id="username" required placeholder="Username">
                            <span class="glyphicon glyphicon-user form-control-feedback"></span>
                        </div>
                        <div class="form-group has-feedback">
                            <label for="password">Password</label>
                            <input type="password" class="form-control" name="password" id="password" required placeholder="Password">
                            <span class="glyphicon glyphicon-lock form-control-feedback"></span>
                        </div>
                        <div class="form-group has-feedback">
                            <label for="confirm_password">Confirm password</label>
                            <input type="password" class="form-control" name="confirm_password" id="confirm_password" required placeholder="Retype password">
                            <span class="glyphicon glyphicon-log-in form-control-feedback"></span>
                        </div>
                        <div class="form-group has-feedback">
                            <label for="country">Country</label>
                            <select class="form-control" name="country" id="country">
                                <option value="Canada">Canada</option>
                                <option value="United States">United States</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal" id="btn_close_modal">
                            <i class="glyphicon glyphicon-remove"></i> Close
                        </button>
                        <button type="submit" name="submit" class="btn btn-success" id="btn_save_changes">
                            <i class="glyphicon glyphicon-ok"></i> Save changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@stop
 
@section('styles')
    <link rel="stylesheet" type="text/css" href="//cdn.datatables.net/1.10.15/css/dataTables.bootstrap.min.css">
@stop
@section('scripts')
    <script src="//cdn.datatables.net/1.10.15/js/jquery.dataTables.min.js"></script>
    <script src="//cdn.datatables.net/1.10.15/js/dataTables.bootstrap.min.js"></script>
    <script src="{{ mix('js/dist/users.js') }}"></script>
@stop