<?php

namespace App\Support;

use App\Models\Cable;
use App\Models\Concerns\Terminates;
use App\Models\Outlet;
use App\Models\Port;
use Illuminate\Database\Eloquent\Model;

/**
 * How a cable end and a cable itself are handed to the front end. One shape,
 * whether it is a link on a port row or a step in a full trace.
 */
final class Terminations
{
    /**
     * @return array<string, mixed>
     */
    public static function describe(Model&Terminates $end): array
    {
        if ($end instanceof Outlet) {
            $end->loadMissing('workplace.room');

            return [
                'kind' => 'outlet',
                'id' => $end->id,
                'label' => $end->label,
                'media' => $end->media,
                'workplace' => [
                    'id' => $end->workplace->id,
                    'name' => $end->workplace->name,
                    'person' => $end->workplace->person,
                    'room' => $end->workplace->room?->name,
                ],
            ];
        }

        /** @var Port $end */
        $end->loadMissing('device.deviceModel');

        return [
            'kind' => 'port',
            'id' => $end->id,
            'name' => $end->name,
            'role' => $end->role,
            'media' => $end->media,
            'description' => $end->description,
            'device' => [
                'id' => $end->device->id,
                'name' => $end->device->name,
                'kind' => $end->device->deviceModel->kind,
                'model' => "{$end->device->deviceModel->vendor} {$end->device->deviceModel->model}",
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function describeCable(Cable $cable): array
    {
        return [
            'kind' => 'cable',
            'id' => $cable->id,
            'media' => $cable->media,
            'strands' => $cable->strands,
            'label' => $cable->label,
            'length_cm' => $cable->length_cm,
            'color' => $cable->color,
            'status' => $cable->status,
        ];
    }

    /**
     * What is at the far end of the cable plugged in here, if anything.
     *
     * @return array{cable: array<string, mixed>, far: array<string, mixed>}|null
     */
    public static function link(Model&Terminates $end): ?array
    {
        $cable = $end->cable();

        if (! $cable instanceof Cable) {
            return null;
        }

        return [
            'cable' => self::describeCable($cable),
            'far' => self::describe($cable->otherEnd($end)),
        ];
    }
}
