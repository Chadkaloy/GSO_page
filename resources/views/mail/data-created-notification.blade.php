<x-mail::message>
# Data Created Notification

Hello,

We are pleased to inform you that new data has been successfully created in the system. Below are the details:

**Record ID:** {{ $id }}  
@foreach ($data as $field => $value)
**{{ ucfirst($field) }}:** {{ $value }}
@endforeach


<x-mail::button :url="$url">
View Details
</x-mail::button>

If you have any questions, feel free to contact us.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>