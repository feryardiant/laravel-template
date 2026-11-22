@foreach ($tags as $tag)
    <x-ui.select.option :value="$tag['id']">{{ $tag['name'] }}</x-ui.select.option>
@endforeach
