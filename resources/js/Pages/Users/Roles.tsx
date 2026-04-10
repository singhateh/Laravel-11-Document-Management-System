import { useState, useEffect } from 'react';
import { usePage } from '@inertiajs/react';
import RolePermissions from '@/Components/RolePermissions';
import PrimaryButton from '@/Components/PrimaryButton';
import DangerButton from '@/Components/DangerButton';
import { PageProps as BasePageProps } from '@/types';

interface User {
  id: number;
  name: string;
  email: string;
  username: string;
  role: string;
  created_at: string;
}

type PageProps = BasePageProps;

export default function Roles() {
  const { auth } = usePage<PageProps>().props;
  const [users, setUsers] = useState<User[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [editingUserId, setEditingUserId] = useState<number | null>(null);
  const [newRole, setNewRole] = useState<string>('');
  const [showPermissions, setShowPermissions] = useState<string | null>(null);
  const [roles, setRoles] = useState<string[]>([]);

  const isAdminOrOwner = auth.user.role === 'admin' || auth.user.role === 'owner';

  useEffect(() => {
    const fetchUsersAndRoles = async () => {
      try {
        setLoading(true);
        setError(null);
        
        const [usersResponse, rolesResponse] = await Promise.all([
          window.axios.get('/api/users'),
          window.axios.get('/api/roles'),
        ]);
        
        const usersData = usersResponse.data;
        const rolesData = rolesResponse.data;
        
        setUsers(usersData);
        setRoles(rolesData);
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Failed to fetch data');
      } finally {
        setLoading(false);
      }
    };

    fetchUsersAndRoles();
  }, []);

  const handleUpdateRole = async (userId: number) => {
    try {
      const response = await window.axios.put(`/api/users/${userId}/role`, {
        role: newRole,
      });

      const updatedUser = response.data;
      
      setUsers(users.map(user => 
        user.id === userId ? { ...user, role: updatedUser.user.role } : user
      ));
      
      setEditingUserId(null);
      setNewRole('');
    } catch (err: any) {
      setError(err.response?.data?.message || err.message || 'Failed to update role');
    }
  };

  const handleCancelEdit = () => {
    setEditingUserId(null);
    setNewRole('');
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    });
  };

  if (!isAdminOrOwner) {
    return (
      <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div className="text-center">
          <h2 className="text-xl font-semibold text-gray-900 mb-2">
            Role Management
          </h2>
          <p className="text-gray-600">
            You do not have permission to access this page. Role management is only available to
            admins and owners.
          </p>
        </div>
      </div>
    );
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center py-12">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
        <span className="ml-2 text-gray-600">Loading...</span>
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

  return (
    <div className="space-y-6">
      <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div className="flex items-center justify-between mb-6">
          <div>
            <h2 className="text-2xl font-bold text-gray-900">Role Management</h2>
            <p className="text-gray-600 mt-1">
              Manage user roles and permissions in your organization.
            </p>
          </div>
        </div>

        {showPermissions && (
          <div className="mb-6">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-lg font-medium text-gray-900">
                Permissions for {showPermissions} Role
              </h3>
              <button
                onClick={() => setShowPermissions(null)}
                className="text-sm text-gray-500 hover:text-gray-700"
              >
                Close
              </button>
            </div>
            <RolePermissions role={showPermissions} />
          </div>
        )}

        <div className="mb-4">
          <div className="flex items-center space-x-4">
            <h3 className="text-lg font-medium text-gray-900">Role Permissions</h3>
            {roles.map(role => (
              <button
                key={role}
                onClick={() => setShowPermissions(showPermissions === role ? null : role)}
                className="px-3 py-1 rounded-full text-sm font-medium bg-indigo-100 text-indigo-800 hover:bg-indigo-200"
              >
                {role}
              </button>
            ))}
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  User
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Role
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Created At
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {users.map(user => (
                <tr key={user.id}>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="flex items-center">
                      <div className="flex-shrink-0 h-10 w-10">
                        <div className="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center">
                          <span className="text-indigo-700 font-medium">
                            {user.name.charAt(0).toUpperCase()}
                          </span>
                        </div>
                      </div>
                      <div className="ml-4">
                        <div className="text-sm font-medium text-gray-900">
                          {user.name}
                        </div>
                        <div className="text-sm text-gray-500">
                          {user.email}
                        </div>
                      </div>
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    {editingUserId === user.id ? (
                      <div className="flex items-center space-x-2">
                        <select
                          value={newRole}
                          onChange={(e) => setNewRole(e.target.value)}
                          className="border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm"
                        >
                          <option value="">Select Role</option>
                          {roles.map(role => (
                            <option key={role} value={role}>
                              {role.charAt(0).toUpperCase() + role.slice(1)}
                            </option>
                          ))}
                        </select>
                        <PrimaryButton
                          onClick={() => handleUpdateRole(user.id)}
                          disabled={!newRole}
                        >
                          Save
                        </PrimaryButton>
                        <DangerButton onClick={handleCancelEdit}>
                          Cancel
                        </DangerButton>
                      </div>
                    ) : (
                      <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium capitalize bg-green-100 text-green-800">
                        {user.role}
                      </span>
                    )}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {formatDate(user.created_at)}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    {editingUserId === user.id ? null : (
                      <button
                        onClick={() => {
                          setEditingUserId(user.id);
                          setNewRole(user.role);
                        }}
                        className="text-indigo-600 hover:text-indigo-900"
                      >
                        Edit Role
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
