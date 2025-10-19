/* istanbul ignore next */
const objectsEqual = (obj1, obj2) => {
  // If both are strictly equal, return true
  if (obj1 === obj2) {
    return true
  }

  // If either is not an object or is null, return false (handles primitive types and null)
  if (typeof obj1 !== 'object' || obj1 === null || typeof obj2 !== 'object' || obj2 === null) {
    return false
  }

  // Get the keys of both objects
  const keys1 = Object.keys(obj1)
  const keys2 = Object.keys(obj2)

  // If they have a different number of keys, they're not equal
  if (keys1.length !== keys2.length) {
    return false
  }

  // Compare each key-value pair recursively
  for (let key of keys1) {
    // Check if both objects have the same key
    if (!keys2.includes(key)) {
      return false
    }

    // Recursively compare the values
    if (!objectsEqual(obj1[key], obj2[key])) {
      return false
    }
  }

  return true
}

export default objectsEqual