<?php

namespace App\Http\Controllers;

use App\Http\Resources\OfficeResource;
use App\Models\Office;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\OfficePendingApproval;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use OfficeValidator;
use Symfony\Component\HttpFoundation\Response;

class OfficeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $offices = Office::query()
            ->where('approval_status', Office::APPROVAL_APPROVED)
            ->where('hidden', false)
            ->when(request('user_id'), fn ($builder)
            => $builder->whereUserId(request('user_id')))
            ->when(request('visitor_id'), fn ($builder)
            => $builder->whereRelation('reservations', 'user_id', '=', request('visitor_id')))
            ->when(
                request('lat') && request('lng'),
                fn ($builder) => $builder->nearestTo(request('lat'), request('lng')),
                fn ($builder) => $builder->orderBy('id', 'ASC')
            )
            ->with(['images', 'tags', 'user'])
            ->withCount(['reservations' => fn ($builder) => $builder->where('status', Reservation::STATUS_ACTIVE)])
            ->paginate(20);

        return OfficeResource::collection(
            $offices
        );
    }

    public function show(Office $office): JsonResource
    {
        $office->loadCount(['reservations' => fn ($builder) => $builder->where('status', Reservation::STATUS_ACTIVE)])
            ->load(['images', 'tags', 'user']);

        return OfficeResource::make($office);
    }

    public function create(): JsonResource
    {
        abort_unless(auth()->user()->tokenCan('office.create'), Response::HTTP_FORBIDDEN);

        $data = (new OfficeValidator())->validate($office = new Office(), request()->all());

        $data['user_id'] = auth()->id();
        $data['approval_status'] = Office::APPROVAL_PENDING;

        $office = DB::transaction(function () use ($office, $data) {
            $office->fill(
                Arr::except($data, ['tags'])
            )->save();

            if(isset($data['tags'])){

                $office->tags()->attach($data['tags']);
            }
            return $office;
        });
        return OfficeResource::make($office->load(['images', 'tags', 'user']));
    }

    public function update(Office $office): JsonResource
    {
        abort_unless(auth()->user()->tokenCan('office.update'), Response::HTTP_FORBIDDEN);

        $this->authorize('update', $office);
        $data = (new OfficeValidator())->validate($office, request()->all());

        // $data['approval_status'] = Office::APPROVAL_PENDING;
        $office->fill(Arr::except($data, ['tags']));
        if($requiresReview = $office->isDirty(['lat', 'lng', 'price_per_day'])){
            $office->fill(['approval_status' => Office::APPROVAL_PENDING]);
        }
        DB::transaction(function () use ($office, $data) {
            $office->save();
            if (isset($data['tags'])) {
                $office->tags()->sync($data['tags']);
            }
        });
        if($requiresReview){
            Notification::send(User::firstWhere('name', 'mohamed'), new OfficePendingApproval($office));
        }
        return OfficeResource::make($office->load(['images', 'tags', 'user']));
    }

    public function delete(Office $office)
    {
        abort_unless(auth()->user()->tokenCan('office.delete'), Response::HTTP_FORBIDDEN);
        $this->authorize('delete', $office);
        throw_if(
            $office->reservations()->where('status', Reservation::STATUS_ACTIVE)->exists(),
            ValidationException::withMessages(['office', 'Cannot delete this Office!'])
        );
        $office->delete();
    }
}
