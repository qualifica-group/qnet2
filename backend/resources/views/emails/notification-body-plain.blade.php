{!! $plainBody !!}

@foreach ($introLines as $line)
{!! $line !!}

@endforeach
@foreach ($details as $row)
{!! $row['label'] !!}: {!! $row['value'] !!}
@endforeach
@isset($actionText)

{!! $actionText !!}: {!! $actionUrl !!}
@endisset
