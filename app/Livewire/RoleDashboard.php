<?php

namespace App\Livewire;

use App\Enums\UserRole;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class RoleDashboard extends Component
{
    public function render()
    {
        $user = Auth::user();
        $role = $user?->role;

        // Testing toggle: when enabled, the dashboard exposes all items.
        $skipGuards = (bool) config('hms.skip_role_page_guards');

        // In skip mode, doctor pages are still only valid for users linked to a doctor profile.
        $doctorLinked = (bool) $user?->doctor;

        $receptionEnabled = $skipGuards || in_array($role, [UserRole::Staff, UserRole::Admin], true);
        $ownerEnabled = $skipGuards || $role === UserRole::Owner;
        $doctorEnabled = $skipGuards ? $doctorLinked : $role === UserRole::Doctor;
        $adminEnabled = $skipGuards || $role === UserRole::Admin;
        $financeEnabled = $skipGuards || $role === UserRole::FinanceManager;

        $sections = [
            [
                'id' => 'reception',
                'title' => __('Reception'),
                'description' => __('Shift and patient flow tools.'),
                'badgeColor' => 'emerald',
                'headerGradientClass' => 'bg-gradient-to-br from-zinc-50 via-white to-emerald-50/40 dark:from-zinc-900 dark:via-zinc-950 dark:to-emerald-950/25',
                'items' => [
                    [
                        'key' => 'reception.shifts',
                        'label' => __('Shift'),
                        'description' => __('Open/close shift, log expenses.'),
                        'href' => route('reception.shifts'),
                        'enabled' => $receptionEnabled,
                    ],
                    [
                        'key' => 'reception.walk-in',
                        'label' => __('Walk-in'),
                        'description' => __('Register walk-in patients.'),
                        'href' => route('reception.walk-in'),
                        'enabled' => $receptionEnabled,
                    ],
                    [
                        'key' => 'reception.appointments',
                        'label' => __('Appointments'),
                        'description' => __('Book and manage appointments.'),
                        'href' => route('reception.appointments'),
                        'enabled' => $receptionEnabled,
                    ],
                    [
                        'key' => 'reception.doctor-share-out',
                        'label' => __('Doc share out'),
                        'description' => __('Log payouts and doctor share out.'),
                        'href' => route('reception.doctor-share-out'),
                        'enabled' => $receptionEnabled,
                    ],
                    [
                        'key' => 'queues.index',
                        'label' => __('Queues'),
                        'description' => __('View all queues and call next.'),
                        'href' => route('queues.index'),
                        'enabled' => $receptionEnabled,
                    ],
                ],
            ],
            [
                'id' => 'owner',
                'title' => __('Owner'),
                'description' => __('Shift history and daily totals.'),
                'badgeColor' => 'amber',
                'headerGradientClass' => 'bg-gradient-to-br from-zinc-50 via-white to-amber-50/35 dark:from-zinc-900 dark:via-zinc-950 dark:to-amber-950/25',
                'items' => [
                    [
                        'key' => 'owner.shifts',
                        'label' => __('Shifts'),
                        'description' => __('Review opened and closed shifts.'),
                        'href' => route('owner.shifts'),
                        'enabled' => $ownerEnabled,
                    ],
                ],
            ],
            [
                'id' => 'doctor',
                'title' => __('Doctor'),
                'description' => __('Your profile, payouts, and today queue.'),
                'badgeColor' => 'emerald',
                'headerGradientClass' => 'bg-gradient-to-br from-zinc-50 via-white to-emerald-50/35 dark:from-zinc-900 dark:via-zinc-950 dark:to-emerald-950/25',
                'items' => [
                    [
                        'key' => 'doctor.dashboard',
                        'label' => __('Doctor home'),
                        'description' => __('Welcome dashboard and active queues.'),
                        'href' => route('doctor.dashboard'),
                        'enabled' => $doctorEnabled,
                    ],
                    [
                        'key' => 'doctor.profile',
                        'label' => __('My profile'),
                        'description' => __('Services and schedule details.'),
                        'href' => route('doctor.profile'),
                        'enabled' => $doctorEnabled,
                    ],
                    [
                        'key' => 'doctor.payouts',
                        'label' => __('My payouts'),
                        'description' => __('Doctor share payouts and history.'),
                        'href' => route('doctor.payouts'),
                        'enabled' => $doctorEnabled,
                    ],
                    [
                        'key' => 'doctor.queue',
                        'label' => __('Today\'s queue'),
                        'description' => __('Tokens assigned to you today.'),
                        'href' => route('doctor.queue'),
                        'enabled' => $doctorEnabled,
                    ],
                    [
                        'key' => 'doctor.processes',
                        'label' => __('Processes'),
                        'description' => __('Your procedures and payment status by date range.'),
                        'href' => route('doctor.processes'),
                        'enabled' => $doctorEnabled,
                    ],
                ],
            ],
            [
                'id' => 'admin',
                'title' => __('Admin'),
                'description' => __('Setup core data and users.'),
                'badgeColor' => 'rose',
                'headerGradientClass' => 'bg-gradient-to-br from-zinc-50 via-white to-rose-50/40 dark:from-zinc-900 dark:via-zinc-950 dark:to-rose-950/25',
                'items' => [
                    [
                        'key' => 'admin.services',
                        'label' => __('Services'),
                        'description' => __('Manage services and pricing rules.'),
                        'href' => route('admin.services'),
                        'enabled' => $adminEnabled,
                    ],
                    [
                        'key' => 'admin.doctors',
                        'label' => __('Doctors'),
                        'description' => __('Manage doctors and assignments.'),
                        'href' => route('admin.doctors'),
                        'enabled' => $adminEnabled,
                    ],
                    [
                        'key' => 'admin.users',
                        'label' => __('Users'),
                        'description' => __('Assign roles and activation status.'),
                        'href' => route('admin.users'),
                        'enabled' => $adminEnabled,
                    ],
                    [
                        'key' => 'admin.service-prices',
                        'label' => __('Service prices'),
                        'description' => __('Configure doctor share percentages.'),
                        'href' => route('admin.service-prices'),
                        'enabled' => $adminEnabled,
                    ],
                ],
            ],
            [
                'id' => 'finance',
                'title' => __('Finance manager'),
                'description' => __('Audits, money trail, shifts, and exports.'),
                'badgeColor' => 'violet',
                'headerGradientClass' => 'bg-gradient-to-br from-zinc-50 via-white to-violet-50/35 dark:from-zinc-900 dark:via-zinc-950 dark:to-violet-950/25',
                'items' => [
                    [
                        'key' => 'finance.dashboard',
                        'label' => __('Finance dashboard'),
                        'description' => __('Revenue, expenses, payouts, and implied net.'),
                        'href' => route('finance.dashboard'),
                        'enabled' => $financeEnabled,
                    ],
                    [
                        'key' => 'owner.shifts',
                        'label' => __('Shifts'),
                        'description' => __('Today’s shift and closed shift history.'),
                        'href' => route('owner.shifts'),
                        'enabled' => $financeEnabled,
                    ],
                    [
                        'key' => 'invoices.index',
                        'label' => __('Invoices'),
                        'description' => __('Search and verify invoice register.'),
                        'href' => route('invoices.index'),
                        'enabled' => $financeEnabled,
                    ],
                    [
                        'key' => 'finance.money-trail',
                        'label' => __('Money trail'),
                        'description' => __('Collections, expenses, and payouts in order.'),
                        'href' => route('finance.money-trail'),
                        'enabled' => $financeEnabled,
                    ],
                    [
                        'key' => 'finance.expenses',
                        'label' => __('Shift expenses'),
                        'description' => __('Expense lines across shifts.'),
                        'href' => route('finance.expenses'),
                        'enabled' => $financeEnabled,
                    ],
                    [
                        'key' => 'finance.ledger',
                        'label' => __('Doctor payout ledger'),
                        'description' => __('Recorded payout batches and details.'),
                        'href' => route('finance.ledger'),
                        'enabled' => $financeEnabled,
                    ],
                    [
                        'key' => 'finance.audit',
                        'label' => __('Audit'),
                        'description' => __('Discounts, cancellations, shift closes.'),
                        'href' => route('finance.audit'),
                        'enabled' => $financeEnabled,
                    ],
                    [
                        'key' => 'finance.exports',
                        'label' => __('Exports'),
                        'description' => __('CSV downloads for accounting.'),
                        'href' => route('finance.exports'),
                        'enabled' => $financeEnabled,
                    ],
                    [
                        'key' => 'reception.doctor-share-out',
                        'label' => __('Doc share audit'),
                        'description' => __('Review unpaid shares and payout history (read-only).'),
                        'href' => route('reception.doctor-share-out'),
                        'enabled' => $financeEnabled,
                    ],
                ],
            ],
        ];

        $roleLabel = $this->roleLabel($role);
        $roleBadgeColor = $this->roleBadgeColor($role);

        return view('livewire.role-dashboard', [
            'roleLabel' => $roleLabel,
            'roleBadgeColor' => $roleBadgeColor,
            'sections' => $sections,
        ]);
    }

    private function roleLabel(?UserRole $role): string
    {
        return match ($role) {
            UserRole::Staff => __('Reception (staff)'),
            UserRole::Admin => __('Admin'),
            UserRole::Owner => __('Owner'),
            UserRole::Doctor => __('Doctor'),
            UserRole::FinanceManager => __('Finance manager'),
            default => __('User'),
        };
    }

    private function roleBadgeColor(?UserRole $role): string
    {
        return match ($role) {
            UserRole::Admin => 'rose',
            UserRole::Staff => 'zinc',
            UserRole::Doctor => 'emerald',
            UserRole::Owner => 'amber',
            UserRole::FinanceManager => 'violet',
            default => 'zinc',
        };
    }
}
