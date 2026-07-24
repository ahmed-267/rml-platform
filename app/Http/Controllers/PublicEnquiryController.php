<?php

namespace App\Http\Controllers;

use App\Enums\HomeownerEnquiryStatus;
use App\Enums\MessageThreadCategory;
use App\Enums\MessageThreadStatus;
use App\Enums\UserRole;
use App\Http\Requests\ContactEnquiryRequest;
use App\Http\Requests\HomeownerEnquiryRequest;
use App\Models\HomeownerEnquiry;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\User;
use App\Support\ReferenceGenerator;
use Illuminate\Http\RedirectResponse;

class PublicEnquiryController extends Controller
{
    public function homeowner(HomeownerEnquiryRequest $request): RedirectResponse
    {
        HomeownerEnquiry::query()->create([
            'reference' => ReferenceGenerator::homeownerEnquiry(),
            'name' => $request->string('full_name')->toString(),
            'phone' => $request->string('phone')->toString(),
            'email' => $request->string('email')->toString(),
            'address' => $request->string('property_address')->toString(),
            'postcode' => $request->string('postcode')->toString(),
            'service_interested_in' => $request->string('service')->toString(),
            'message' => $request->input('message'),
            'consent' => true,
            'status' => HomeownerEnquiryStatus::New,
        ]);

        return back()->with(
            'success',
            __('rml.enquiries.homeowner_success'),
        );
    }

    public function contact(ContactEnquiryRequest $request): RedirectResponse
    {
        $admin = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', UserRole::SuperAdmin->value))
            ->orderBy('id')
            ->first();

        if ($admin) {
            $thread = MessageThread::query()->create([
                'thread_reference' => ReferenceGenerator::thread(),
                'subject' => $request->string('subject')->toString(),
                'category' => MessageThreadCategory::Internal,
                'status' => MessageThreadStatus::Open,
                'created_by_user_id' => $admin->id,
                'assigned_to_user_id' => $admin->id,
            ]);

            Message::query()->create([
                'message_thread_id' => $thread->id,
                'sender_user_id' => $admin->id,
                'body' => implode("\n", [
                    'Public contact form submission',
                    'Name: '.$request->string('name')->toString(),
                    'Email: '.$request->string('email')->toString(),
                    'Enquiry type: '.$request->string('enquiry_type')->toString(),
                    '',
                    $request->string('message')->toString(),
                ]),
            ]);
        }

        return back()->with(
            'success',
            __('rml.enquiries.contact_success'),
        );
    }
}
