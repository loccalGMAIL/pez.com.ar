<div
    x-data="{
        installPrompt: window.pezInstallPrompt ?? null,
        async install() {
            this.installPrompt.prompt();
            await this.installPrompt.userChoice;
            this.installPrompt = window.pezInstallPrompt = null;
        },
    }"
    x-on:pez-installable.window="installPrompt = window.pezInstallPrompt"
    x-on:pez-installed.window="installPrompt = null"
    x-show="installPrompt"
    x-cloak
>
    <x-filament::icon-button
        icon="heroicon-o-arrow-down-tray"
        label="Instalar app"
        tooltip="Instalar app"
        color="gray"
        x-on:click="install()"
    />
</div>
