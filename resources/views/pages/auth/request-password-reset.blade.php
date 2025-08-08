<form wire:submit="request">
    <input type="text" name="username" placeholder="Email" wire:model="username" />
    @error('username') {{ $message }} @enderror
    <button type="submit">Enviar</button>
</form>
