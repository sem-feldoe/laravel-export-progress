# Add Broadcast events to your exports

## About Laravel export progress
Example

```php
final class UsersExport extends AbstractExport implements FromQuery
{
    /**
     * @param User $user
     */
    public function map($user): array
    {
        try {
            $this->sendProgressEventIfNeeded();
        } catch (Exception $exception) {
            Log::error(__('Failed to send progress event => :message', ['message' => $exception->getMessage()]));
        }

        return [
            $user->id,
            $user->username,
            $user->email,
        ];
    }

    public function query(): Builder
    {
        return User::query();
    }
}
```

```vue
const startExport = (data) => {
    exportSystem.start(data.uuid, data.name)
}

Echo.private(`exports.${this.user.id}`)
.listen('.export.progressed', e => {
    exportSystem.progress(
        e.uuid,
        e.type,
        e.model?.name,
        e.progress,
        e.estimated_duration
    )
})
.listen('.export.completed', e => {
    exportStore.finish(payload.uuid)

    notify({
    title: tr(`success.${payload.type}`),
    type: 'valid'
    })
    
    window.open(payload.url)
})
.listen('.export.failed', e => {
    exportSystem.fail(
        e.uuid,
        e.reason.message
    )
})
```

## License

Laravel export progress is open-sourced software licensed under the [MIT license](LICENSE.md).
