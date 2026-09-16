<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PriorityMatrixRule;
use Illuminate\Http\Request;

class PriorityMatrixController extends Controller
{
    public function index()
    {
        $rules = PriorityMatrixRule::all()->keyBy(fn ($r) => "{$r->impact}:{$r->urgency}");

        return view('admin.priority-matrix.index', compact('rules'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'rules' => ['required', 'array'],
            'rules.*' => ['required', 'in:P1,P2,P3,P4'],
        ]);

        foreach ($data['rules'] as $key => $priority) {
            [$impact, $urgency] = explode(':', $key);
            PriorityMatrixRule::updateOrCreate(['impact' => $impact, 'urgency' => $urgency], ['priority' => $priority]);
        }

        AuditLog::record($request->user(), 'priority_matrix.updated', new PriorityMatrixRule, null, $data['rules']);

        return redirect()->route('admin.priority-matrix.index')->with('status', 'Priority matrix updated.');
    }
}
