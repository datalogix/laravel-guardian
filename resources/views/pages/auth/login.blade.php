<form wire:submit="authenticate">
    <input type="email" name="email" placeholder="Email" wire:model="email" />
    @error('email') {{ $message }} @enderror
    <input type="password" name="password" placeholder="Senha" wire:model="password" />
    @error('password') {{ $message }} @enderror
    <button type="submit">Enviar</button>
</form>
