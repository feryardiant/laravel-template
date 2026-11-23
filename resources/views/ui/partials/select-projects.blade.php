@foreach ($projects as $project)
    <x-ui.select.option :value="$project['id']">{{ $project['name'] }}</x-ui.select.option>
@endforeach
