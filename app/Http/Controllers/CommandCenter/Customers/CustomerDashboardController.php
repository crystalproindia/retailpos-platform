<?php
namespace App\Http\Controllers\CommandCenter\Customers; use App\Http\Controllers\Controller; use App\Services\Customers\CustomerDashboardService; use Illuminate\Http\Request; use Illuminate\View\View;
class CustomerDashboardController extends Controller { public function __invoke(Request $request,CustomerDashboardService $service):View{return view('command-center.customers.dashboard',['dashboard'=>$service->data($request->user()->company_id)]);} }
