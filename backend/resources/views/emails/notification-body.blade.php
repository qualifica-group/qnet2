{{-- A record notification opened by an "Modello email" the administrator chose
     (spec 0390), composed by App\Support\Notifications\RecordMail::composeWithBody:
     the template body (already rendered and sanitized), the sentence, the detail
     card, then the button that opens the record. --}}
@extends('emails.layout')

@section('content')
    {!! $body !!}

    @foreach ($introLines as $line)
        <p>{{ $line }}</p>
    @endforeach

    @if ($details !== [])
        <table class="details" role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0">
            @foreach ($details as $row)
                <tr>
                    <td class="details-label">{{ $row['label'] }}</td>
                    <td class="details-value">{{ $row['value'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @isset($actionText)
        @include('emails.partials.button', ['url' => $actionUrl, 'label' => $actionText])

        @include('emails.partials.link-fallback', ['url' => $actionUrl])
    @endisset
@endsection
