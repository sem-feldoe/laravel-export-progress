# Add Broadcast events to your exports

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
