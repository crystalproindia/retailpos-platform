<?php
namespace App\Http\Controllers\CommandCenter\Finance;
use App\Http\Controllers\Controller; use App\Models\Finance\ExpenseCategory; use App\Services\Finance\ExpenseCategoryProvisioner; use Illuminate\Http\Request; use Illuminate\Validation\Rule;
class ExpenseCategoryController extends Controller {
 public function index(Request $r, ExpenseCategoryProvisioner $categories){ abort_unless($r->user()->can('finance.expense_categories.manage'),403); $categories->provision($r->user()->company); return view('command-center.finance.expense-categories.index',['categories'=>ExpenseCategory::where('company_id',$r->user()->company_id)->withCount('transactions')->orderBy('sort_order')->orderBy('name')->paginate(30)]); }
 public function create(Request $r){ abort_unless($r->user()->can('finance.expense_categories.manage'),403); return view('command-center.finance.expense-categories.form',['category'=>new ExpenseCategory(['is_active'=>true])]); }
 public function store(Request $r){ $this->authorize(); $data=$this->data($r); ExpenseCategory::create($data+['company_id'=>$r->user()->company_id,'is_system'=>false]); return redirect()->route('finance.expense-categories.index')->with('status','Category created.'); }
 public function edit(Request $r,ExpenseCategory $expenseCategory){ $this->owned($r,$expenseCategory); return view('command-center.finance.expense-categories.form',['category'=>$expenseCategory]); }
 public function update(Request $r,ExpenseCategory $expenseCategory){ $this->owned($r,$expenseCategory); $expenseCategory->update($this->data($r)); return redirect()->route('finance.expense-categories.index')->with('status','Category updated. Historical transactions retain their posted classification.'); }
 private function authorize():void { abort_unless(request()->user()->can('finance.expense_categories.manage'),403); }
 private function owned(Request $r,ExpenseCategory $c):void { $this->authorize(); abort_unless($c->company_id===$r->user()->company_id,404); }
 private function data(Request $r):array { return $r->validate(['name'=>['required','string','max:120',Rule::unique('expense_categories','name')->where('company_id',$r->user()->company_id)->ignore($r->route('expenseCategory'))],'classification'=>['required',Rule::in([ExpenseCategory::OPERATING_EXPENSE,ExpenseCategory::OTHER_EXPENSE,ExpenseCategory::OTHER_INCOME])],'is_active'=>['nullable','boolean']])+['is_active'=>$r->boolean('is_active')]; }
}
