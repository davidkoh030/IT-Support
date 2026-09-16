<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Calendar;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function index()
    {
        return view('admin.calendars.index', ['calendars' => Calendar::with('holidays')->orderBy('name')->get()]);
    }

    public function create()
    {
        return view('admin.calendars.form', ['calendar' => new Calendar]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $calendar = Calendar::create($data);
        $this->syncHolidays($request, $calendar);

        return redirect()->route('admin.calendars.index')->with('status', 'Calendar created.');
    }

    public function edit(Calendar $calendar)
    {
        return view('admin.calendars.form', ['calendar' => $calendar->load('holidays')]);
    }

    public function update(Request $request, Calendar $calendar)
    {
        $data = $this->validated($request);
        $calendar->update($data);
        $this->syncHolidays($request, $calendar);

        return redirect()->route('admin.calendars.index')->with('status', 'Calendar updated.');
    }

    public function destroy(Calendar $calendar)
    {
        abort(422, 'Calendars in use by SLA policies or sites cannot be deleted from the pilot UI.');
    }

    private function syncHolidays(Request $request, Calendar $calendar): void
    {
        $calendar->holidays()->delete();
        foreach ($request->input('holiday_dates', []) as $i => $date) {
            if (empty($date)) {
                continue;
            }
            $calendar->holidays()->create([
                'date' => $date,
                'name' => $request->input('holiday_names.'.$i, 'Holiday'),
            ]);
        }
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'timezone' => ['required', 'string', 'max:64'],
            'working_days' => ['required', 'array'],
            'working_days.*' => ['integer', 'min:1', 'max:7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ]);
        $data['working_days'] = array_map('intval', $data['working_days']);

        return $data;
    }
}
