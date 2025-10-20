# Contributing to Uniform Monitoring System

First off, thank you for considering contributing to the Uniform Monitoring System! It's people like you that make this system better for educational institutions worldwide.

## 🤝 Code of Conduct

This project and everyone participating in it is governed by our Code of Conduct. By participating, you are expected to uphold this code.

## 🚀 How Can I Contribute?

### Reporting Bugs

Before creating bug reports, please check existing issues as you might find that the bug has already been reported. When creating a bug report, include as many details as possible:

- **Use a clear and descriptive title**
- **Describe the exact steps to reproduce the problem**
- **Provide specific examples to demonstrate the steps**
- **Describe the behavior you observed and what behavior you expected**
- **Include screenshots if applicable**
- **Include system information** (OS, PHP version, Python version, browser)

### Suggesting Enhancements

Enhancement suggestions are tracked as GitHub issues. When creating an enhancement suggestion:

- **Use a clear and descriptive title**
- **Provide a step-by-step description of the suggested enhancement**
- **Provide specific examples to demonstrate the steps**
- **Describe the current behavior and expected behavior**
- **Explain why this enhancement would be useful**

### Pull Requests

1. Fork the repository
2. Create a new branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Add tests for your changes if applicable
5. Ensure all tests pass
6. Commit your changes (`git commit -m 'Add amazing feature'`)
7. Push to the branch (`git push origin feature/amazing-feature`)
8. Open a Pull Request

## 🛠️ Development Setup

1. **Clone the repository**
```bash
git clone https://github.com/yourusername/uniform-monitoring-system.git
cd uniform-monitoring-system
```

2. **Set up PHP environment**
```bash
composer install
```

3. **Set up Python environment**
```bash
cd ai-detection
python -m venv venv
source venv/bin/activate  # Linux/Mac
venv\Scripts\activate     # Windows
pip install -r requirements.txt
```

4. **Configure database**
```bash
# Copy example configuration
cp db.php.example db.php
# Edit with your database credentials
```

5. **Run tests**
```bash
# PHP tests (if available)
vendor/bin/phpunit

# Python tests
cd ai-detection
python -m pytest
```

## 📝 Coding Standards

### PHP Code Style
- Follow PSR-12 coding standard
- Use meaningful variable and function names
- Add comments for complex logic
- Validate all user inputs
- Use prepared statements for database queries

```php
// Good
function getUserById($userId) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Bad
function getUser($id) {
    return mysqli_query($conn, "SELECT * FROM users WHERE id = $id");
}
```

### Python Code Style
- Follow PEP 8 style guide
- Use type hints where appropriate
- Add docstrings for functions and classes
- Handle exceptions properly

```python
# Good
def detect_uniform(image_path: str) -> Dict[str, Any]:
    """
    Detect uniform components in the given image.
    
    Args:
        image_path: Path to the image file
        
    Returns:
        Dictionary containing detection results
    """
    try:
        # Detection logic here
        return {"status": "success", "detections": []}
    except Exception as e:
        logger.error(f"Detection failed: {e}")
        return {"status": "error", "message": str(e)}
```

### JavaScript Code Style
- Use ES6+ features
- Use meaningful variable names
- Add comments for complex logic
- Handle errors appropriately

```javascript
// Good
async function fetchStudentData(studentId) {
    try {
        const response = await fetch(`/api/students/${studentId}`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return await response.json();
    } catch (error) {
        console.error('Failed to fetch student data:', error);
        throw error;
    }
}
```

## 🧪 Testing Guidelines

### Writing Tests
- Write tests for new features
- Ensure tests cover edge cases
- Use descriptive test names
- Mock external dependencies

### Running Tests
```bash
# Run all PHP tests
composer test

# Run Python tests
cd ai-detection
python -m pytest tests/

# Run JavaScript tests (if available)
npm test
```

## 📋 Commit Message Guidelines

Use clear and descriptive commit messages:

- **feat**: A new feature
- **fix**: A bug fix
- **docs**: Documentation changes
- **style**: Code style changes (formatting, etc.)
- **refactor**: Code refactoring
- **test**: Adding or updating tests
- **chore**: Maintenance tasks

Examples:
```
feat: add CSV import validation for student data
fix: resolve camera detection timeout issue
docs: update installation guide for Windows
style: format PHP code according to PSR-12
refactor: optimize database query performance
test: add unit tests for penalty calculation
chore: update dependencies to latest versions
```

## 🔒 Security Guidelines

- Never commit sensitive information (passwords, API keys, etc.)
- Validate and sanitize all user inputs
- Use prepared statements for database queries
- Implement proper authentication and authorization
- Follow OWASP security guidelines

## 📚 Documentation

- Update documentation for new features
- Include code examples where appropriate
- Use clear and concise language
- Test documentation steps

## 🎯 Priority Areas for Contribution

We especially welcome contributions in these areas:

1. **Security Enhancements**
   - Password hashing improvements
   - Two-factor authentication
   - Rate limiting implementation

2. **Performance Optimization**
   - Database query optimization
   - AI model optimization
   - Frontend performance improvements

3. **Feature Enhancements**
   - Multi-camera support
   - Advanced reporting features
   - Mobile application development

4. **Testing**
   - Unit test coverage
   - Integration tests
   - Performance testing

5. **Documentation**
   - API documentation
   - Deployment guides
   - Video tutorials

## 💬 Getting Help

- **GitHub Issues**: For bugs and feature requests
- **GitHub Discussions**: For questions and general discussion
- **Email**: technical-support@uniformmonitoring.com

## 📄 License

By contributing to this project, you agree that your contributions will be licensed under the same MIT License that covers the project.

## 🙏 Recognition

Contributors will be recognized in the project's README and release notes. Significant contributions may be featured in our documentation and project website.

Thank you for contributing to the Uniform Monitoring System! 🎉