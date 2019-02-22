@extends('master')

@section('container')
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <h1>
        User Management
      </h1>
      <ol class="breadcrumb">
        <li><a href="{{ url('/dashboard')}} "><i class="fa fa-dashboard"></i> Dashboard</a></li>
        <li><a href="{{ route('admin.users') }}"><i class="fa fa-dashboard"></i> Users</a></li>
        <li class="active">Roles</li>
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
                        <a class="btn btn-info" id="btn_add_new"><i class="fa fa-plus"></i> Add new</a><br/>
                    </div>
                    <!-- /.box-header -->
                    <div class="box-body table-responsive">
                        <table class="table table-bordered table-hover" id="tbl_roles">
                            <thead>
                                <tr>
                                    <th>Name</th>
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
                <form role="form" method="post" action="" id="form_roles">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" id="myModalLabel">View/Edit</h4>
                    </div>
                    <div class="modal-body">  
                        <input type="hidden" name="id" id="role_id" value="new" />        
                        <input type="hidden" name="_token" id="_token" value="{{csrf_token()}}" /> 
                        <div class="form-group">
                            <label for="type_name">Name</label>
                            <input type="text" name="type_name" id="type_name" class="form-control" required />
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
    <script src="{{ mix('js/dist/roles.js') }}"></script>
@stop