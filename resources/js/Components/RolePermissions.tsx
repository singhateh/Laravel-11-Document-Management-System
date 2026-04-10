import { useState, useEffect } from 'react';

interface RolePermissionsProps {
  role: string;
}

interface PermissionResource {
  [key: string]: string[];
}

export default function RolePermissions({ role }: RolePermissionsProps) {
  const [permissions, setPermissions] = useState<PermissionResource | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchPermissions = async () => {
      try {
        setLoading(true);
        setError(null);
        const response = await window.axios.get(`/api/roles/${role}/permissions`);
        const data = response.data;
        setPermissions(data);
      } catch (err: any) {
        setError(err.response?.data?.message || err.message || 'Failed to fetch permissions');
      } finally {
        setLoading(false);
      }
    };

    if (role) {
      fetchPermissions();
    }
  }, [role]);

  if (loading) {
    return (
      <div className="flex items-center justify-center py-8">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
        <span className="ml-2 text-gray-600">Loading permissions...</span>
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
        Error: {error}
      </div>
    );
  }

  if (!permissions || Object.keys(permissions).length === 0) {
    return (
      <div className="text-gray-500 text-center py-8">
        No permissions found for this role.
      </div>
    );
  }

  const resourceNames = {
    user: 'Users',
    document: 'Documents',
    folder: 'Folders',
    category: 'Categories',
    tag: 'Tags',
    comment: 'Comments',
    notification: 'Notifications',
    file_request: 'File Requests',
    share: 'Shares',
    stego_document: 'Stego Documents',
    system: 'System',
    team: 'Team',
    profile: 'Profile',
  };

  const permissionNames = {
    create: 'Create',
    read: 'Read',
    update: 'Update',
    delete: 'Delete',
    manage: 'Manage',
    view_logs: 'View Logs',
    configure_settings: 'Configure Settings',
    invite_user: 'Invite User',
    remove_user: 'Remove User',
    update_profile: 'Update Profile',
    change_password: 'Change Password',
  };

  return (
    <div className="space-y-6">
      <h3 className="text-lg font-medium text-gray-900">Permissions for {role}</h3>
      
      <div className="space-y-4">
        {Object.entries(permissions).map(([resource, perms]) => (
          <div key={resource} className="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <h4 className="font-medium text-gray-900 mb-3">
              {resourceNames[resource as keyof typeof resourceNames] || resource}
            </h4>
            
            <div className="flex flex-wrap gap-2">
              {perms.map((permission) => (
                <span
                  key={permission}
                  className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800"
                >
                  {permissionNames[permission as keyof typeof permissionNames] || permission}
                </span>
              ))}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
