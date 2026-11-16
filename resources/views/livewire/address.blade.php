<div class="space-y-6">
    <flux:input
        wire:model="address.line"
        type="text"
        :label="__('Address line')"
        autocomplete="address-line1"
    />

    <div class="grid gap-6 sm:grid-cols-2">
        <flux:input
            wire:model="address.rt"
            type="text"
            inputmode="numeric"
            maxlength="3"
            :label="__('RT')"
            autocomplete="off"
        />

        <flux:input
            wire:model="address.rw"
            type="text"
            inputmode="numeric"
            maxlength="3"
            :label="__('RW')"
            autocomplete="off"
        />
    </div>

    <flux:input
        wire:model.live.debounce.400ms="address.postal_code"
        type="text"
        inputmode="numeric"
        maxlength="5"
        :label="__('Postal code')"
        autocomplete="postal-code"
    />

    <x-ui.select
        wire:model.live="address.province_code"
        :label="__('Province')"
        :placeholder="__('Select province')"
        clearable
    >
        @foreach ($this->provinces as $province)
            <x-ui.select.option :value="$province->code">{{ $province->name }}</x-ui.select.option>
        @endforeach
    </x-ui.select>

    <div wire:key="address-regency-{{ $address['province_code'] }}">
        <x-ui.select
            wire:model.live="address.regency_code"
            :label="__('Regency')"
            :placeholder="__('Select regency')"
            :disabled="blank($address['province_code'])"
            clearable
        >
            @foreach ($this->regencies as $regency)
                <x-ui.select.option :value="$regency->code">{{ $regency->name }}</x-ui.select.option>
            @endforeach
        </x-ui.select>
    </div>

    <div wire:key="address-district-{{ $address['regency_code'] }}">
        <x-ui.select
            wire:model.live="address.district_code"
            :label="__('District')"
            :placeholder="__('Select district')"
            :disabled="blank($address['regency_code'])"
            clearable
        >
            @foreach ($this->districts as $district)
                <x-ui.select.option :value="$district->code">{{ $district->name }}</x-ui.select.option>
            @endforeach
        </x-ui.select>
    </div>

    <div wire:key="address-village-{{ $address['district_code'] }}">
        <x-ui.select
            wire:model.live="address.village_code"
            :label="__('Village')"
            :placeholder="__('Select village')"
            :disabled="blank($address['district_code'])"
            clearable
        >
            @foreach ($this->villages as $village)
                <x-ui.select.option :value="$village->code">{{ $village->name }}</x-ui.select.option>
            @endforeach
        </x-ui.select>
    </div>
</div>
