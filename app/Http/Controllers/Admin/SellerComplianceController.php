<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductApproval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SellerComplianceController extends Controller
{
    public function reviews()
    {
        // Only show pending items that need a decision
        $approvals = ProductApproval::with(['product', 'seller.sellerProfile'])
            ->where('status', 'Pending')
            ->latest()
            ->paginate(10);
            
        return view('admin.compliance.reviews', compact('approvals'));
    }

    public function approve(int $id)
    {
        $approval = ProductApproval::findOrFail($id);
        $approval->update([
            'status' => 'Approved',
            'disapproval_type' => null,
            'reviewed_by' => Auth::guard('admin')->id(),
            'remarks' => null
        ]);

        return back()->with('success', 'Product approved and is now live.');
    }

    public function disapprove(Request $request, int $id)
    {
        $request->validate([
            'disapproval_type' => 'required|in:Violation,Warning',
            'remarks' => 'required|string|max:500'
        ]);
        
        $approval = ProductApproval::findOrFail($id);
        $approval->update([
            'status' => 'Disapproved',
            'disapproval_type' => $request->disapproval_type,
            'reviewed_by' => Auth::guard('admin')->id(),
            'remarks' => $request->remarks
        ]);

        return back()->with('success', "Product rejected and moved to {$request->disapproval_type}s.");
    }

    public function violations()
    {
        $violations = ProductApproval::with(['product', 'seller.sellerProfile', 'reviewer'])
            ->where('status', 'Disapproved')
            ->where('disapproval_type', 'Violation')
            ->latest()
            ->paginate(10);

        return view('admin.compliance.violations', compact('violations'));
    }

    public function warnings()
    {
        $warnings = ProductApproval::with(['product', 'seller.sellerProfile', 'reviewer'])
            ->where('status', 'Disapproved')
            ->where('disapproval_type', 'Warning')
            ->latest()
            ->paginate(10);

        return view('admin.compliance.warnings', compact('warnings'));
    }
}