{{-- Every notification email about a record (spec 0381), composed by
     App\Support\Notifications\RecordMail: the sentence, the detail card, then
     the one button that opens the record's module. --}}
@extends('emails.layout')

@section('content')
    <h1>{{ $subject }}</h1>

    <p>{{ $greeting }}</p>

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

    @foreach ($outroLines as $line)
        <p>{{ $line }}</p>
    @endforeach

    @isset($actionText)
        @include('emails.partials.button', ['url' => $actionUrl, 'label' => $actionText])

        @include('emails.partials.link-fallback', ['url' => $actionUrl])
    @endisset
@endsection
