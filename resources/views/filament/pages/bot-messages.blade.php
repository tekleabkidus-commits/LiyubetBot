<x-filament-panels::page>
<div class="ly-rewards ly-message-editor">
    <section class="ly-hero">
        <div><div class="ly-eyebrow">LIYUBET / CONTENT STUDIO</div><h1>Bot message manager</h1>
            <p>Edit every fixed Telegram reply independently, personalize with placeholders, and attach a separate image to each message. No coding required.</p>
        </div>
        <span class="ly-action" style="pointer-events:none">{{ $messageCount }} editable messages</span>
    </section>

    <div class="ly-hint">
        <span>✎</span>
        <div><strong>How to use:</strong> Choose a message on the left, edit its text, optionally upload an image, and click <strong>Save message</strong>. Changes affect new bot replies only. The optional second referral promotion is disabled until you activate it. Dynamic campaign, automation, menu and keyword-rule content continues to use its existing editors.</div>
    </div>

    <div class="ly-editor-layout">
        <aside class="ly-panel ly-message-sidebar" aria-label="Choose a Telegram message to edit">
            <div class="ly-panel-head"><div><h2>Message library</h2><p>Select one item to edit</p></div></div>
            <div class="ly-editor-list">
                @foreach($groups as $groupName => $entries)
                    <div class="ly-editor-group">
                        <h3>{{ $groupName }} <span>{{ count($entries) }}</span></h3>
                        @foreach($entries as $key => $row)
                            <button type="button" class="ly-editor-item {{ $selectedKey === $key ? 'is-active' : '' }}"
                                wire:click="selectMessage('{{ $key }}')"
                                wire:confirm="Switch to this message? Unsaved edits in the current message will be discarded."
                                aria-current="{{ $selectedKey === $key ? 'true' : 'false' }}">
                                <span class="ly-editor-indicator">✦</span>
                                <span>{{ $row['title'] }}<small>{{ \Illuminate\Support\Str::limit($row['description'], 66) }}</small></span>
                            </button>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </aside>
        <div class="ly-message-main">
            <section class="ly-panel">
                <header class="ly-panel-head">
                    <div><span class="ly-eyebrow" style="color:#8b5cf6">{{ strtoupper($selectedMessage['group']) }} / MESSAGE</span>
                        <h2 style="margin-top:5px">{{ $selectedMessage['title'] }}</h2>
                        <p>{{ $selectedMessage['description'] }}</p>
                    </div>
                    <span class="ly-pill {{ ($selectedMessage['optional'] ?? false) ? 'ly-review' : 'ly-ok' }}">{{ ($selectedMessage['optional'] ?? false) ? 'Optional' : 'Standard message' }}</span>
                </header>
                <div class="ly-panel-body ly-form-shell">
                    @if(count($selectedMessage['tokens']) > 0)
                    <div class="ly-hint" style="margin-bottom:16px">
                        <span>{ }</span><div><strong>Available personalization fields:</strong>
                            <div class="ly-editor-tokens">
                                @foreach($selectedMessage['tokens'] as $token)
                                    <code>{{ '{'.$token.'}' }}</code>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif
                    <form wire:submit="save">
                        {{ $this->form }}
                        <div class="ly-form-footer" style="margin-top:20px">
                            <p>Save updates only <strong>{{ $selectedMessage['title'] }}</strong>. Removing its image does not delete images attached to other messages.</p>
                            <div class="ly-tools">
                                <button type="button" class="ly-action ly-ghost" wire:click="resetEditor" wire:confirm="Discard unsaved edits?">Discard edits</button>
                                <button type="submit" class="ly-action ly-primary" wire:loading.attr="disabled" wire:target="save">✓ Save message</button>
                            </div>
                        </div>
                    </form>
                </div>
            </section>
            <section class="ly-panel">
                <header class="ly-panel-head">
                    <div><h2>Telegram preview</h2><p>Sample placeholders shown below; only Save publishes changes</p></div><span class="ly-pill">Preview</span>
                </header>
                <div class="ly-preview-stage">
                    <div class="ly-telegram-bubble">
                        <span class="ly-telegram-label">LiyubetBot</span>
                        @if($currentMedia)<div class="ly-preview-media">📷 Current attachment: {{ basename($currentMedia->path) }}</div>@endif
                        <div class="ly-preview-text">{{ $previewText }}</div>
                        <small>Sample · {{ now()->format('H:i') }}</small>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <section class="ly-panel">
        <div class="ly-panel-head"><div><h2>Other message editors</h2><p>Dynamic content already managed separately in your backoffice</p></div></div>
        <div class="ly-panel-body ly-grid ly-grid-3">
            <a class="ly-action" href="{{ \App\Filament\Resources\KeywordReplies\KeywordReplyResource::getUrl() }}" wire:navigate>Keyword replies and images →</a>
            <a class="ly-action" href="{{ \App\Filament\Resources\MenuItems\MenuItemResource::getUrl() }}" wire:navigate>Custom menu labels →</a>
            <a class="ly-action" href="{{ \App\Filament\Pages\RewardSettings::getUrl() }}" wire:navigate>Sport, Casino, Referral buttons →</a>
        </div>
    </section>
</div>
</x-filament-panels::page>