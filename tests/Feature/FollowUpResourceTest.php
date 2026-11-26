<?php

namespace Tests\Feature;

use App\Enums\FollowUpStatus;
use App\Filament\Resources\FollowUps\FollowUpResource;
use App\Filament\Resources\FollowUps\Pages\ListFollowUps;
use App\Filament\Resources\FollowUps\Pages\ViewFollowUp;
use App\Models\Client;
use App\Models\FollowUp;
use App\Models\User;
use App\Support\Settings;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FollowUpResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_pending_tab_lists_who_is_waiting_for_a_follow_up_oldest_first(): void
    {
        $this->actingAs(User::factory()->create());
        $upcoming = FollowUp::factory()->create(['due_on' => today()->addDays(3)]);
        $overdue = FollowUp::factory()->create(['due_on' => today()->subDays(2)]);
        $sent = FollowUp::factory()->status(FollowUpStatus::Sent)->create();

        Livewire::test(ListFollowUps::class)
            ->assertCanSeeTableRecords([$overdue, $upcoming], inOrder: true)
            ->assertCanNotSeeTableRecords([$sent]);
    }

    public function test_the_sent_tab_keeps_the_record_of_messages_sent(): void
    {
        $this->actingAs(User::factory()->create());
        $older = FollowUp::factory()->status(FollowUpStatus::Sent)->create(['due_on' => today()->subMonth()]);
        $newer = FollowUp::factory()->status(FollowUpStatus::Sent)->create(['due_on' => today()->subWeek()]);
        $pending = FollowUp::factory()->create();

        Livewire::test(ListFollowUps::class)
            ->set('activeTab', 'enviados')
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true)
            ->assertCanNotSeeTableRecords([$pending]);
    }

    public function test_the_page_shows_the_text_it_was_sent_with_even_after_changing_the_settings(): void
    {
        $this->actingAs(User::factory()->create());
        $followUp = FollowUp::factory()->for(Client::factory()->create(['business_name' => 'Estudio Pérez']))->create();
        $followUp->markAsSent();
        $sentText = $followUp->message;
        Settings::set(['follow_ups.work_order_message' => 'Otro texto']);

        $this->get(FollowUpResource::getUrl('view', ['record' => $followUp]))
            ->assertOk()
            ->assertSee('Mensaje enviado')
            ->assertSee(explode("\n", $sentText)[0])
            ->assertDontSee('Otro texto');
    }

    public function test_the_page_previews_the_message_of_a_pending_follow_up(): void
    {
        $this->actingAs(User::factory()->create());
        Settings::set(['follow_ups.work_order_message' => 'Hola {nombre}, cómo anda todo?']);
        $followUp = FollowUp::factory()->for(Client::factory()->create(['business_name' => 'Estudio Pérez', 'trade_name' => null]))->create();

        $this->get(FollowUpResource::getUrl('view', ['record' => $followUp]))
            ->assertOk()
            ->assertSee('Mensaje que se va a enviar')
            ->assertSee('Hola Estudio Pérez, cómo anda todo?');
    }

    public function test_a_skipped_follow_up_can_be_proposed_again(): void
    {
        $this->actingAs(User::factory()->create());
        $followUp = FollowUp::factory()->status(FollowUpStatus::Skipped)->create(['due_on' => today()->subWeek()]);

        Livewire::test(ViewFollowUp::class, ['record' => $followUp->getRouteKey()])
            ->callAction(TestAction::make('postponeFollowUp'), data: ['due_on' => today()->toDateString()])
            ->assertHasNoFormErrors();

        $followUp->refresh();
        $this->assertSame(FollowUpStatus::Pending, $followUp->status);
        $this->assertTrue($followUp->due_on->isToday());
    }

    public function test_a_sent_follow_up_opens_whatsapp_again_with_the_same_text(): void
    {
        $user = User::factory()->create();
        $followUp = FollowUp::factory()->for(Client::factory()->create(['phone' => '011 2345-6789']))->create();
        $followUp->markAsSent();
        $sentAt = $followUp->sent_at->toDateTimeString();
        Settings::set(['follow_ups.work_order_message' => 'Otro texto']);
        $this->travel(1)->hour();

        $this->actingAs($user)
            ->get(route('filament.admin.follow-ups.whatsapp', $followUp))
            ->assertRedirect('https://wa.me/5491123456789?text='.rawurlencode($followUp->message));

        $this->assertSame($sentAt, $followUp->refresh()->sent_at->toDateTimeString());
    }

    public function test_the_menu_badge_counts_the_follow_ups_due_today(): void
    {
        FollowUp::factory()->count(2)->create();
        FollowUp::factory()->create(['due_on' => today()->addDay()]);
        FollowUp::factory()->status(FollowUpStatus::Sent)->create();

        $this->assertSame('2', FollowUpResource::getNavigationBadge());
    }
}
