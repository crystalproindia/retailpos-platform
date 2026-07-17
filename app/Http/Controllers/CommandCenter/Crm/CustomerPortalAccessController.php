<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmCustomer;
use App\Models\Crm\CrmCustomerPortalUser;
use App\Repositories\Crm\CrmCustomerRepository;
use App\Services\Portal\CustomerPortalAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerPortalAccessController extends Controller
{
    public function invite(Request $request, CrmCustomerRepository $customers, CustomerPortalAccessService $access, int $customer): RedirectResponse
    {
        $record = $customers->findForUser($request->user(), $customer);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:80']]);
        $invite = $access->invite($request->user(), $record, $data);

        return back()->with('portalInviteUrl', $invite['url'])->with('status', 'Portal access link created. Copy it to share securely with the customer.');
    }

    public function refresh(Request $request, CrmCustomerRepository $customers, CustomerPortalAccessService $access, int $customer, int $portalUser): RedirectResponse
    {
        $record = $customers->findForUser($request->user(), $customer);
        $user = $record->portalUsers()->findOrFail($portalUser);

        return back()->with('portalInviteUrl', $access->issueLoginLink($user))->with('status', 'A new one-time portal access link is ready to copy.');
    }

    public function status(Request $request, CrmCustomerRepository $customers, CustomerPortalAccessService $access, int $customer, int $portalUser): RedirectResponse
    {
        $record = $customers->findForUser($request->user(), $customer);
        $user = $record->portalUsers()->findOrFail($portalUser);
        $status = $request->validate(['status' => ['required', 'in:active,suspended']])['status'];
        $access->setStatus($user, $status);

        return back()->with('status', $status === 'suspended' ? 'Portal access suspended.' : 'Portal access reactivated.');
    }
}
