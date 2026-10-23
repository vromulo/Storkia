<?php

namespace App\Livewire\Buyer;

use App\Models\IdentityVerification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class IdentityVerificationFlow extends Component
{
    use WithFileUploads;

    // View State: 'intro', 'method', 'id_type', 'upload', 'camera', 'review', 'submitted'
    public string $currentScreen = 'intro';

    public ?string $method = null; // 'upload' or 'camera'
    public ?string $id_type = null; // 'drivers_license', 'passport', 'identity_card'

    // Files (temporary uploads via Livewire)
    public $front_image;
    public $back_image;

    protected function rules(): array
    {
        $rules = [
            'id_type'     => ['required', 'in:drivers_license,passport,identity_card'],
            'method'      => ['required', 'in:upload,camera'],
            'front_image' => ['required', 'image', 'mimes:jpeg,jpg,png', 'max:10240'],
        ];

        if ($this->id_type !== 'passport') {
            $rules['back_image'] = ['required', 'image', 'mimes:jpeg,jpg,png', 'max:10240'];
        }

        return $rules;
    }

    public function mount(): void
    {
        $latest = Auth::user()->identityVerification;
        
        // Prevent accessing the flow if already verified or currently under review
        if ($latest && in_array($latest->status, ['verified', 'pending'], true)) {
            $this->redirect(route('user.account-management'), navigate: false);
        }
    }

    public function start(): void
    {
        $this->currentScreen = 'method';
    }

    public function selectMethod(string $method): void
    {
        $this->method = $method;
        $this->reset(['front_image', 'back_image']);
        $this->resetValidation();
        $this->currentScreen = 'id_type';
    }

    public function selectIdType(string $type): void
    {
        $this->id_type = $type;
        $this->reset(['front_image', 'back_image']);
        $this->resetValidation();
        $this->currentScreen = $this->method === 'camera' ? 'camera' : 'upload';
    }

    public function cancelCamera(): void
    {
        // Reset any previously captured front/back image if the buyer cancels mid-way
        $this->reset(['front_image', 'back_image']);
        $this->resetValidation();
        $this->currentScreen = 'id_type';
    }

    public function retake(): void
    {
        $this->reset(['front_image', 'back_image']);
        $this->resetValidation();
        $this->currentScreen = $this->method ?: 'method';
    }

    public function proceedToReview(): void
    {
        $this->validate([
            'front_image' => ['required', 'image', 'mimes:jpeg,jpg,png', 'max:10240'],
            'back_image'  => $this->id_type === 'passport' ? ['nullable'] : ['required', 'image', 'mimes:jpeg,jpg,png', 'max:10240'],
        ], [
            'front_image.required' => 'Please provide the front of your document.',
            'back_image.required'  => 'Please provide the back of your document.',
        ]);

        $this->currentScreen = 'review';
    }

    public function submit(): void
    {
        $this->validate();

        $user = Auth::user();

        // 1. Store securely on private 'local' disk
        $frontPath = $this->front_image->store('identity_documents/fronts', 'local');
        $backPath = ($this->id_type !== 'passport' && $this->back_image)
            ? $this->back_image->store('identity_documents/backs', 'local')
            : null;

        // 2. Create append-only historical attempt record
        IdentityVerification::create([
            'user_id'          => $user->id,
            'id_type'          => $this->id_type,
            'method'           => $this->method,
            'front_image_path' => $frontPath,
            'back_image_path'  => $backPath,
            'status'           => 'pending',
            'rejection_reason' => null,
            'verified_at'      => null,
        ]);

        $this->currentScreen = 'submitted';
    }

    public function render()
    {
        return view('livewire.buyer.identity-verification-flow')
            ->layout('layouts.app', ['hideFooter' => true]);
    }
}