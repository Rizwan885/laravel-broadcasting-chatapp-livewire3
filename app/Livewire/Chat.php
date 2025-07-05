<?php

namespace App\Livewire;

use App\Models\User;
use App\Models\Message;
use Livewire\Component;
use App\Events\UserTyping;
use Livewire\Attributes\On;
use App\Events\UnreadMessage;
use App\Events\ChatMessageEvent;
use Illuminate\Support\Facades\Auth;
use Livewire\WithFileUploads;
use Symfony\Component\Mailer\Event\MessageEvent;

class Chat extends Component
{
  use WithFileUploads;
  public $user;
  public $message;
  public $senderId;
  public $receiverId;
  public $messages;
  public $file;
  public function mount($userId)
  {

    $this->user =  $this->getUserId($userId);
    $this->senderId = Auth::user()->id;
    $this->receiverId = $userId;
    $this->messages = $this->getMessages();
    # Read Messages
    $this->markMessagesAsRead();
  }

  public function render()
  {
    # Read Messages
    $this->markMessagesAsRead();
    return view('livewire.chat');
  }
  public function getUserId($userId)
  {
    return User::findOrFail($userId);
  }
  public function sendMessage()
  {
    // Save the message
    $sentMessage = $this->saveMessage();
    $this->messages[] = $sentMessage;
    broadcast(new ChatMessageEvent($sentMessage));

    $this->message = null;
    $this->file = null;

    # Calculate unread messages for the receiver
    $unreadCount = $this->getUnreadMessagesCount();

    $this->dispatch('messages-updated');
    # Broadcast unread message count
    broadcast(new UnreadMessage($this->receiverId, $this->senderId, $unreadCount))->toOthers();
  }
  public function saveMessage()
  {
    $filePath         = null;
    $fileOriginalName = null;
    $fileName         = null;
    $fileType         = null;


    if ($this->file) {
      $fileOriginalName = $this->file->getClientOriginalName();
      $fileName         = $this->file->hashName();
      $filePath         = $this->file->store('chat_files', 'public');
      $fileType         = $this->file->getMimeType();
    }
    return Message::create([
      'message' => $this->message,
      'sender_id' => $this->senderId,
      'receiver_id' => $this->receiverId,
      'file_name'          => $fileName,
      'file_original_name' => $fileOriginalName,
      'folder_path'          => $filePath,
      'file_type'          => $fileType,
    ]);
  }

  public function getMessages()
  {
    return  Message::with('sender:id,name', 'receiver:id,name')->where(function ($query) {
      $query->where('sender_id', $this->senderId)
        ->where('receiver_id', $this->receiverId);
    })->orWhere(function ($query) {
      $query->where('sender_id', $this->receiverId)
        ->where('receiver_id', $this->senderId);
    })
      ->get();
  }


  #[On('echo-private:chat-channel.{senderId},ChatMessageEvent')]
  public function listenMessage($event)
  {
    $newMessage = Message::find($event['message']['id'])->load('sender:id,name', 'receiver:id,name');
    $this->messages[] = $newMessage;
  }
  public function userTyping()
  {
    broadcast(new UserTyping($this->senderId, $this->receiverId))->toOthers();
  }

  /**
   * Function: markMessagesAsRead
   */
  public function markMessagesAsRead()
  {
    Message::where('receiver_id', $this->senderId)
      ->where('sender_id', $this->receiverId)
      ->where('is_read', false)
      ->update(['is_read' => true]);

    # Broadcast unread message count
    broadcast(new UnreadMessage($this->senderId, $this->receiverId, 0))->toOthers();
  }

  /**
   * Function: getUnreadMessagesCount
   * @return unreadMessagesCount
   */
  public function getUnreadMessagesCount()
  {
    return Message::where('receiver_id', $this->receiverId)
      ->where('is_read', false)
      ->count();
  }
}
