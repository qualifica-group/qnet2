{!! $subject !!}

{!! $greeting !!}

@foreach ($introLines as $line)
{!! $line !!}

@endforeach
@foreach ($details as $row)
{!! $row['label'] !!}: {!! $row['value'] !!}
@endforeach
@foreach ($outroLines as $line)

{!! $line !!}
@endforeach
@isset($actionText)

{!! $actionText !!}: {!! $actionUrl !!}
@endisset
