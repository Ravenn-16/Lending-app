@props(['status'])
@php($class = ['ACTIVE' => 'primary', 'DUE_SOON' => 'warning', 'OVERDUE' => 'danger', 'FULLY_PAID' => 'success'][$status] ?? 'secondary')
<span class="badge bg-{{ $class }}">{{ ['ACTIVE' => 'Not due yet', 'DUE_SOON' => 'Due within 3 days', 'OVERDUE' => 'Past due date', 'FULLY_PAID' => 'Fully paid'][$status] ?? $status }}</span>
