<?php

namespace Database\Seeders\Auth;

use App\Events\Backend\UserCreated;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use App\Models\Address;
use App\Models\UserMultiProfile;
use Laravolt\Avatar\Facade as Avatar;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Class UserTableSeeder.
 */
class UserTableSeeder extends Seeder
{
    /**
     * Run the database seed.
     *
     * @return void
     */
    public function run()
    {
        Schema::disableForeignKeyConstraints();

        // Add the master administrator, user id of 1
        $avatarPath = config('app.avatar_base_path');

         if(env('IS_DUMMY_DATA')==false){



             $users = [

            [
                'username' => 'ezwaytv-admin',
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'email' => 'ezwaynetwork@gmail.com',
                'password' => Hash::make('ezway@123'),
                'mobile' => '+12123567890',
                'date_of_birth' => fake()->date,
                'file_url' => '/dummy-images/profile/admin/super_admin.png',
                'gender' => 'female',
                'email_verified_at' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'user_type' => 'admin',
                'seed_roles' => ['super-admin', 'admin'],
                'is_subscribe' => 0,
                'country_code' => 91,    
            ]

         ];



         }else{




        $users = [



    [
                'username' => 'ezwaytv-admin',
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'email' => 'ezwaynetwork@gmail.com',
                'password' => Hash::make('ezway@123'),
                'mobile' => '+12123567890',
                'date_of_birth' => fake()->date,
                'file_url' => '/dummy-images/profile/admin/super_admin.png',
                'gender' => 'male',
                'email_verified_at' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'user_type' => 'admin',
                'seed_roles' => ['super-admin', 'admin'],
                'is_subscribe' => 0,
                'country_code' => 91,    
            ],

                [
                    'username' => 'karim',
                    'first_name' => 'Karim',
                    'last_name' => 'Rahat',
                    'email' => 'rahat@webermelon.com',
                    'password' => Hash::make('rahat@123'),
                    'mobile' => '+8801686591775',
                    'date_of_birth' => fake()->date,
                    'file_url' => '/dummy-images/profile/admin/karim_rahat.png',
                    'gender' => 'male',
                    'email_verified_at' => Carbon::now(),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                     'seed_roles' => ['super-admin', 'admin'],
                    'is_subscribe' => 0,
                    'country_code' => 880,    
                ],


        ];

         }



            foreach ($users as $key => $user_data) {
                $featureImage = $user_data['file_url'] ?? null;
                // Always assign both super-admin and admin roles to the seeded Super Admin for compatibility
                if (
                    isset($user_data['username']) &&
                    in_array($user_data['username'], ['ezwaytv-admin']) &&
                    (in_array('super-admin', $user_data['seed_roles'] ?? []) || $user_data['user_type'] === 'admin')
                ) {
                    $seedRoles = ['super-admin', 'admin'];
                } else {
                    $seedRoles = $user_data['seed_roles'] ?? [$user_data['user_type']];
                }
                $userData = Arr::except($user_data, ['file_url', 'seed_roles']);
                $user = User::create($userData);

                $user->syncRoles($seedRoles);
                event(new UserCreated($user));


                if (isset($featureImage) &&  $featureImage !='') {


                  $profile_image = $this->uploadToSpaces($featureImage);

                 if ($profile_image) {
                    $user->file_url = extractFileNameFromUrl($profile_image,'users');
                  }

                }

                $user->save();

                $this->createOrUpdateProfile($user);
                $this->createOrUpdateChildProfile($user);
            }

            Schema::enableForeignKeyConstraints();
    }

    private function uploadToSpaces($publicPath)
    {

       $localFilePath = public_path($publicPath);
       $remoteFilePath = 'users/image/' . basename($publicPath);

       if (file_exists($localFilePath)) {
           $disk = env('ACTIVE_STORAGE', 'local');

           if ($disk === 'local') {

               Storage::disk($disk)->put('public/' . $remoteFilePath, file_get_contents($localFilePath));

               return asset('storage/' . $remoteFilePath);
           } else {

               Storage::disk($disk)->put($remoteFilePath, file_get_contents($localFilePath));
               return Storage::disk($disk)->url($remoteFilePath);
           }


       }

       return false;
   }

   private function uploadToSpacesAvatar($publicPath)
    {

       $localFilePath = public_path($publicPath);
       $remoteFilePath = 'avatars/image/' . basename($publicPath);

       if (file_exists($localFilePath)) {           // Get the active storage disk from the environment
           $disk = env('ACTIVE_STORAGE', 'local');

           if ($disk === 'local') {
               // Store in the public directory for local storage
               Storage::disk($disk)->put('public/' . $remoteFilePath, file_get_contents($localFilePath));
               return asset('storage/' . $remoteFilePath);
           } else {

               // Upload to the specified storage disk
               Storage::disk($disk)->put($remoteFilePath, file_get_contents($localFilePath));
               return Storage::disk($disk)->url($remoteFilePath);
           }
       }

       return false;
   }



   private function createOrUpdateProfile(User $user)
    {
        $name = $user->first_name . ' ' . $user->last_name;

        UserMultiProfile::updateOrCreate(
            [
                'user_id' => $user->id,
                'is_child_profile' => 0, 
            ],
            [
                'name' => $name,
                'avatar' => $this->uploadToSpacesAvatar('/dummy-images/avatars/icon2.png')
            ]
        );
    }

    private function createOrUpdateChildProfile(User $user)
    {
        $name = 'kids';

        UserMultiProfile::updateOrCreate(
            [
                'user_id' => $user->id,
                'is_child_profile' => 1,
            ],
            [
                'name' => $name,
                'avatar' => $this->uploadToSpacesAvatar('/dummy-images/avatars/icon4.png'),
                'is_child_profile' => 1
            ]
        );
    }

    private function attachFeatureImage($model, $publicPath)
    {
        if (!env('IS_DUMMY_DATA_IMAGE')) return false;

        $file = new \Illuminate\Http\File($publicPath);

        $media = $model->addMedia($file)->preservingOriginal()->toMediaCollection('file_url');

        return $media->getUrl();
    }

    // Generate avatar based on user name and store it
    private function generateAvatar($name)
    {
        $name = $name ?? Str::random(10);

        $fileName = Str::random(10) . '.png';
        $filePath = 'avatars/' . $fileName;

        if (!Storage::exists('public/avatars')) {
            Storage::makeDirectory('public/avatars');
        }

        Avatar::create($name)->save(storage_path('app/public/' . $filePath));

        return asset('storage/' . $filePath);
    }
}
