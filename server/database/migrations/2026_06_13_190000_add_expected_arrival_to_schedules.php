<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_templates', function (Blueprint $table): void {
            $table->time('expected_arrival_time')->nullable()->after('departure_time');
        });

        Schema::table('schedules', function (Blueprint $table): void {
            $table->dateTime('expected_arrival_at')->nullable()->after('departure_time');
        });

        foreach (DB::table('schedule_templates')->get() as $template) {
            $departure = $this->parseTime($template->departure_time);
            $minutes = (int) ($template->duration_minutes ?? 720);
            $arrival = $departure->copy()->addMinutes($minutes);

            DB::table('schedule_templates')
                ->where('id', $template->id)
                ->update(['expected_arrival_time' => $arrival->format('H:i:s')]);
        }

        foreach (DB::table('schedules')->whereNotNull('departure_time')->get() as $schedule) {
            $departure = Carbon::parse($schedule->departure_time, config('app.timezone'));
            $template = $schedule->template_id
                ? DB::table('schedule_templates')->where('id', $schedule->template_id)->first()
                : null;

            if ($template?->expected_arrival_time) {
                $arrival = $this->arrivalOnServiceDate(
                    $departure,
                    $this->parseTime($template->expected_arrival_time)
                );
            } else {
                $minutes = (int) ($template->duration_minutes ?? 720);
                $arrival = $departure->copy()->addMinutes($minutes);
            }

            DB::table('schedules')
                ->where('id', $schedule->id)
                ->update(['expected_arrival_at' => $arrival]);
        }
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table): void {
            $table->dropColumn('expected_arrival_at');
        });

        Schema::table('schedule_templates', function (Blueprint $table): void {
            $table->dropColumn('expected_arrival_time');
        });
    }

    private function parseTime(mixed $time): Carbon
    {
        $timeStr = is_string($time) ? substr($time, 0, 8) : Carbon::parse($time)->format('H:i:s');

        return Carbon::parse('1970-01-01 ' . $timeStr, config('app.timezone'));
    }

    private function arrivalOnServiceDate(Carbon $departure, Carbon $clock): Carbon
    {
        $arrival = $departure->copy()->setTimeFromTimeString($clock->format('H:i:s'));

        if ($arrival <= $departure) {
            $arrival->addDay();
        }

        return $arrival;
    }
};
